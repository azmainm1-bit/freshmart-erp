<?php

namespace App\Support\Sales;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\BusinessDate;
use App\Support\BusinessRules;
use App\Support\Decimal\Money;
use App\Support\DocumentNumber;
use App\Support\Inventory\StockLedger;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public static function post(User $actor, array $input): Sale
    {
        return DB::transaction(function () use ($actor, $input) {
            $shift = ShiftService::lockOpen($actor, $input['shift_id']);
            $customer = empty($input['customer_id']) ? null : Customer::lockForUpdate()->findOrFail($input['customer_id']);
            BusinessRules::require(! $customer || $customer->active, 'customer_id', 'Choose an active customer.');
            BusinessRules::require(count($input['lines']) > 0, 'lines', 'Add at least one product to the cart.');
            $products = Product::whereIn('id', array_column($input['lines'], 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $computed = [];
            $subtotal = Money::zero();
            $tax = Money::zero();
            $discount = Money::zero();
            foreach ($input['lines'] as $i => $line) {
                $product = $products->get($line['product_id']);
                BusinessRules::require($product !== null && ! $product->archived, "lines.{$i}.product_id", 'This product is unavailable. Remove it from the cart.');
                $quantity = BusinessRules::quantity($product, (string) $line['quantity'], "lines.{$i}.quantity");
                $lineDiscount = Money::of((string) ($line['discount_amount'] ?? '0'));
                BusinessRules::require($lineDiscount->isZero() || $actor->can('sales.discount'), "lines.{$i}.discount_amount", 'A manager must post discounted sales.');
                $pricing = SalePricing::line($product, $quantity, $lineDiscount);
                $computed[] = ['product' => $product, 'quantity' => $quantity, 'pricing' => $pricing];
                $subtotal = $subtotal->plus($pricing['subtotal']);
                $tax = $tax->plus($pricing['tax_amount']);
                $discount = $discount->plus($lineDiscount);
            }
            BusinessRules::require($discount->isZero() || ! empty($input['notes']), 'notes', 'Record the reason for the discount.');
            $total = $subtotal->plus($tax);
            if (isset($input['expected_total'])) {
                BusinessRules::require($total->equals((string) $input['expected_total']), 'expected_total', 'Prices have changed. Review the updated cart total before paying.');
            }
            $tendered = Money::zero();
            $cash = Money::zero();
            foreach ($input['payments'] ?? [] as $payment) {
                $amount = Money::of((string) $payment['amount']);
                BusinessRules::require($amount->isPositive(), 'payments', 'Payment amounts must be positive.');
                BusinessRules::require(in_array($payment['method'], ['cash', 'card', 'mobile', 'bank']), 'payments', 'Unknown payment method.');
                BusinessRules::require($payment['method'] === 'cash' || ! empty($payment['reference']), 'payments', 'Non-cash payments require a transaction reference.');
                $tendered = $tendered->plus($amount);
                if ($payment['method'] === 'cash') {
                    $cash = $cash->plus($amount);
                }
            }
            $change = $tendered->isGreaterThan($total) ? $tendered->minus($total) : Money::zero();
            BusinessRules::require($change->isLessThanOrEqualTo($cash), 'payments', 'Non-cash payments cannot be overpaid for cash change.');
            $accepted = $tendered->minus($change);
            $due = $total->minus($accepted);
            if ($due->isPositive()) {
                BusinessRules::require($customer !== null, 'customer_id', 'Select a credit customer or pay the full total.');
                $balance = Money::of((string) DB::table('sales')->where('customer_id', $customer->id)->selectRaw('coalesce(sum(grand_total - returned_amount - paid_amount + refunded_amount), 0)::text as balance')->first()->balance);
                BusinessRules::require($balance->plus($due)->isLessThanOrEqualTo($customer->credit_limit), 'payments', 'This sale exceeds the customer credit limit.');
            }
            $sale = Sale::create([
                'number' => DocumentNumber::next('SALE'), 'shift_id' => $shift->id, 'location_id' => $shift->location_id,
                'cashier_id' => $actor->id, 'customer_id' => $customer?->id, 'subtotal' => $subtotal, 'tax_total' => $tax,
                'discount_total' => $discount, 'grand_total' => $total, 'paid_amount' => $accepted,
                'tendered_amount' => $tendered, 'change_amount' => $change, 'notes' => $input['notes'] ?? null, 'posted_at' => now(),
            ]);
            $saleCost = Money::zero();
            foreach ($computed as $item) {
                $product = $item['product'];
                $quantity = $item['quantity'];
                $saleLine = $sale->lines()->create([
                    'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku,
                    'stock_unit' => $product->stock_unit, 'quantity' => $quantity, 'tax_rate_percent' => $product->tax_rate_percent,
                    'tax_inclusive' => $product->tax_inclusive, ...$item['pricing'],
                ]);
                // FEFO, then undated stock. Lock only balances, not the nullable join side.
                $balances = StockBalance::query()->leftJoin('batches', 'batches.id', '=', 'stock_balances.batch_id')
                    ->where('stock_balances.product_id', $product->id)->where('location_id', $shift->location_id)->where('quantity_on_hand', '>', 0)
                    ->where(fn ($q) => $q->whereNull('batches.expiry_date')->orWhere('batches.expiry_date', '>=', BusinessDate::today()))
                    ->when($product->is_batch_tracked, fn ($q) => $q->whereNotNull('stock_balances.batch_id'))
                    ->select('stock_balances.*')->orderByRaw('batches.expiry_date ASC NULLS LAST')->orderBy('stock_balances.id')->lock('for update of stock_balances')->get();
                $remaining = $quantity;
                $cost = BigDecimal::zero();
                foreach ($balances as $balance) {
                    if ($remaining->isZero()) {
                        break;
                    }
                    $take = $remaining->isLessThan($balance->quantity_on_hand) ? $remaining : $balance->quantity_on_hand;
                    $result = StockLedger::postMovement($product->id, $shift->location_id, $balance->batch_id, $take->negated(), null, StockMovement::SALE, 'Sale', $sale->id, $actor);
                    $saleLine->allocations()->create(['batch_id' => $balance->batch_id, 'quantity' => $take, 'unit_cost' => $result->movement->unit_cost]);
                    $cost = $cost->plus($take->toBigDecimal()->multipliedBy($result->movement->unit_cost->toBigDecimal()));
                    $remaining = $remaining->minus($take);
                }
                if ($remaining->isPositive()) {
                    throw new InsufficientStockException($product->id, (string) $quantity->minus($remaining), (string) $quantity);
                }
                $lineCost = Money::of($cost);
                $saleLine->update(['cost_total' => $lineCost]);
                $saleCost = $saleCost->plus($lineCost);
            }
            $sale->update(['cost_total' => $saleCost]);
            $changeLeft = $change;
            foreach ($input['payments'] ?? [] as $payment) {
                $amount = Money::of((string) $payment['amount']);
                if ($payment['method'] === 'cash' && $changeLeft->isPositive()) {
                    $deduction = $amount->isLessThan($changeLeft) ? $amount : $changeLeft;
                    $amount = $amount->minus($deduction);
                    $changeLeft = $changeLeft->minus($deduction);
                }
                if ($amount->isPositive()) {
                    $sale->payments()->create(['shift_id' => $shift->id, 'actor_id' => $actor->id, 'kind' => 'payment', 'method' => $payment['method'], 'amount' => $amount, 'reference' => $payment['reference'] ?? null, 'paid_at' => now()]);
                }
            }
            AuditLog::record($actor, 'SALE_POSTED', $sale, ['total' => (string) $total, 'discount' => (string) $discount, 'notes' => $input['notes'] ?? null]);

            return $sale->load('lines', 'payments', 'customer', 'cashier:id,name', 'location');
        });
    }
}

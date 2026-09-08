<?php

namespace App\Support\Sales;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Amounts;
use App\Support\AuditLog;
use App\Support\BusinessDate;
use App\Support\BusinessRules;
use App\Support\Decimal\Money;
use App\Support\DocumentNumber;
use App\Support\Inventory\StockLedger;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class SalesReturnService
{
    public static function post(User $actor, Sale $sale, array $input): SalesReturn
    {
        return DB::transaction(function () use ($actor, $sale, $input) {
            $shift = empty($input['shift_id']) ? null : ShiftService::lockOpen($actor, $input['shift_id']);
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->customer_id) {
                Customer::lockForUpdate()->findOrFail($sale->customer_id);
            }
            $lines = $sale->lines()->with('product', 'allocations.batch')->get()->keyBy('id');
            Product::whereIn('id', $lines->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
            $location = Location::findOrFail($input['location_id']);
            BusinessRules::require($input['disposition'] === 'quarantine' ? $location->type === 'quarantine' : $location->id === $sale->location_id, 'location_id', 'Restock at the original sale location, or select a quarantine location for damaged goods.');
            BusinessRules::require(count($input['lines']) > 0, 'lines', 'Choose at least one sale line.');
            $return = SalesReturn::create(['number' => DocumentNumber::next('RET'), 'sale_id' => $sale->id, 'actor_id' => $actor->id, 'location_id' => $location->id, 'reason' => $input['reason'], 'disposition' => $input['disposition'], 'posted_at' => now()]);
            $total = Money::zero();
            $tax = Money::zero();
            $cost = Money::zero();
            foreach ($input['lines'] as $i => $item) {
                $line = $lines->get($item['sale_line_id']);
                BusinessRules::require($line !== null, "lines.{$i}.sale_line_id", 'This line does not belong to the selected sale.');
                $quantity = BusinessRules::quantity($line->product, (string) $item['quantity'], "lines.{$i}.quantity");
                $cumulative = $line->returned_quantity->plus($quantity);
                BusinessRules::require($cumulative->isLessThanOrEqualTo($line->quantity), "lines.{$i}.quantity", 'Quantity exceeds the amount remaining to return.');
                $lineTotal = Amounts::proportion($line->line_total, $cumulative, $line->quantity)->minus(Amounts::proportion($line->line_total, $line->returned_quantity, $line->quantity));
                $lineTax = Amounts::proportion($line->tax_amount, $cumulative, $line->quantity)->minus(Amounts::proportion($line->tax_amount, $line->returned_quantity, $line->quantity));
                $remaining = $quantity;
                foreach ($line->allocations->sortBy('id') as $allocation) {
                    if ($remaining->isZero()) {
                        break;
                    }
                    $available = $allocation->quantity->minus($allocation->returned_quantity);
                    if ($available->isZero()) {
                        continue;
                    }
                    $take = $remaining->isLessThan($available) ? $remaining : $available;
                    BusinessRules::require($input['disposition'] === 'quarantine' || ! $allocation->batch?->expiry_date || $allocation->batch->expiry_date->format('Y-m-d') >= BusinessDate::today(), 'disposition', 'Expired returns must go to quarantine.');
                    StockLedger::postMovement($line->product_id, $location->id, $allocation->batch_id, $take, $allocation->unit_cost, StockMovement::RETURN, 'SalesReturn', $return->id, $actor, note: $input['reason']);
                    $allocation->update(['returned_quantity' => $allocation->returned_quantity->plus($take)]);
                    $remaining = $remaining->minus($take);
                }
                BusinessRules::require($remaining->isZero(), 'lines', 'The original stock allocation does not reconcile. Contact an administrator.');
                $cumulativeCost = BigDecimal::zero();
                foreach ($line->allocations as $allocation) {
                    $cumulativeCost = $cumulativeCost->plus($allocation->returned_quantity->toBigDecimal()->multipliedBy($allocation->unit_cost->toBigDecimal()));
                }
                $previousCost = Money::of((string) SalesReturnLine::where('sale_line_id', $line->id)->sum('cost_amount'));
                $lineCost = Money::of($cumulativeCost)->minus($previousCost);
                $return->lines()->create(['sale_line_id' => $line->id, 'quantity' => $quantity, 'total_amount' => $lineTotal, 'tax_amount' => $lineTax, 'cost_amount' => $lineCost]);
                $line->update(['returned_quantity' => $cumulative]);
                $total = $total->plus($lineTotal);
                $tax = $tax->plus($lineTax);
                $cost = $cost->plus($lineCost);
            }
            $due = $sale->grand_total->minus($sale->returned_amount)->minus($sale->paid_amount)->plus($sale->refunded_amount);
            // Credit notes settle unpaid receivables first; refund only collected money.
            $refund = $total->isGreaterThan($due) ? $total->minus($due) : Money::zero();
            if ($refund->isPositive()) {
                $method = $input['refund_method'];
                BusinessRules::require($method !== 'cash' || $shift !== null, 'shift_id', 'Cash refunds require your open shift.');
                BusinessRules::require($method === 'cash' || ! empty($input['reference']), 'reference', 'Enter the refund transaction reference.');
                if ($method === 'cash') {
                    BusinessRules::require($refund->isLessThanOrEqualTo(ShiftService::expectedCash($shift)), 'refund_method', 'The drawer does not contain enough cash. Record an approved cash-in or use another refund method.');
                }
                $sale->payments()->create(['sales_return_id' => $return->id, 'shift_id' => $shift?->id, 'actor_id' => $actor->id, 'kind' => 'refund', 'method' => $method, 'amount' => $refund, 'reference' => $input['reference'] ?? null, 'paid_at' => now()]);
            }
            $return->update(['total_amount' => $total, 'tax_amount' => $tax, 'cost_amount' => $cost, 'refund_amount' => $refund]);
            $sale->update(['returned_amount' => $sale->returned_amount->plus($total), 'refunded_amount' => $sale->refunded_amount->plus($refund)]);
            AuditLog::record($actor, 'SALES_RETURN_POSTED', $return, ['refund' => (string) $refund, 'credit_reduction' => (string) $total->minus($refund)]);

            return $return->load('lines');
        });
    }
}

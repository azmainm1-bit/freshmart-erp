<?php

namespace App\Support\Purchasing;

use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\BusinessRules;
use App\Support\Decimal\Cost;
use App\Support\Decimal\Money;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public static function create(User $actor, array $input): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $input) {
            $supplier = Supplier::findOrFail($input['supplier_id']);
            BusinessRules::require($supplier->active, 'supplier_id', 'Choose an active supplier.');
            Location::findOrFail($input['location_id']);
            BusinessRules::require(count($input['lines']) > 0, 'lines', 'Add at least one product.');
            $order = PurchaseOrder::create([
                'number' => DocumentNumber::next('PO'), 'supplier_id' => $supplier->id,
                'location_id' => $input['location_id'], 'created_by_id' => $actor->id,
                'expected_on' => $input['expected_on'] ?? null, 'notes' => $input['notes'] ?? null,
                'total_amount' => Money::zero(), 'status' => 'ordered',
            ]);
            $products = Product::whereIn('id', array_column($input['lines'], 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = Money::zero();
            foreach ($input['lines'] as $index => $line) {
                $product = $products->get($line['product_id']);
                BusinessRules::require($product !== null && ! $product->archived, "lines.{$index}.product_id", 'Choose an active product.');
                $quantity = BusinessRules::quantity($product, (string) $line['quantity'], "lines.{$index}.quantity");
                $cost = Cost::of((string) $line['unit_cost']);
                BusinessRules::require(! $cost->isNegative(), "lines.{$index}.unit_cost", 'Cost cannot be negative.');
                $lineTotal = Money::of($quantity->toBigDecimal()->multipliedBy($cost->toBigDecimal()));
                $order->lines()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => $cost, 'line_total' => $lineTotal]);
                $total = $total->plus($lineTotal);
            }
            $order->update(['total_amount' => $total]);
            AuditLog::record($actor, 'PURCHASE_ORDER_CREATED', $order);

            return $order->load('lines.product', 'supplier', 'location');
        });
    }

    public static function cancel(User $actor, PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $order) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($order->id);
            BusinessRules::require($order->status === 'ordered', 'status', 'Only an unreceived order can be cancelled.');
            $order->update(['status' => 'cancelled']);
            AuditLog::record($actor, 'PURCHASE_ORDER_CANCELLED', $order);

            return $order;
        });
    }
}

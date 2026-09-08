<?php

namespace App\Support\Purchasing;

use App\Models\Batch;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\BusinessDate;
use App\Support\BusinessRules;
use App\Support\Decimal\Cost;
use App\Support\Decimal\Money;
use App\Support\DocumentNumber;
use App\Support\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

class ReceivingService
{
    public static function post(User $actor, array $input): GoodsReceipt
    {
        return DB::transaction(function () use ($actor, $input) {
            $supplier = Supplier::findOrFail($input['supplier_id']);
            BusinessRules::require($supplier->active, 'supplier_id', 'Choose an active supplier.');
            $location = Location::findOrFail($input['location_id']);
            BusinessRules::require(count($input['lines']) > 0, 'lines', 'Add at least one product.');
            $order = empty($input['purchase_order_id']) ? null : PurchaseOrder::lockForUpdate()->findOrFail($input['purchase_order_id']);
            if ($order) {
                BusinessRules::require(in_array($order->status, ['ordered', 'partial']), 'purchase_order_id', 'This order cannot receive more stock.');
                BusinessRules::require($order->supplier_id === $supplier->id && $order->location_id === $location->id, 'purchase_order_id', 'Supplier and receiving location must match the order.');
            }
            $receipt = GoodsReceipt::create([
                'number' => DocumentNumber::next('GR'), 'supplier_id' => $supplier->id,
                'location_id' => $location->id, 'purchase_order_id' => $order?->id,
                'supplier_reference' => $input['supplier_reference'] ?? null,
                'notes' => $input['notes'] ?? null, 'received_by_id' => $actor->id, 'posted_at' => now(),
                'total_amount' => Money::zero(),
            ]);
            $products = Product::whereIn('id', array_column($input['lines'], 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = Money::zero();
            foreach ($input['lines'] as $i => $line) {
                $product = $products->get($line['product_id']);
                BusinessRules::require($product !== null && ! $product->archived, "lines.{$i}.product_id", 'Choose an active product.');
                $quantity = BusinessRules::quantity($product, (string) $line['quantity_received'], "lines.{$i}.quantity_received");
                $cost = Cost::of((string) $line['unit_cost']);
                BusinessRules::require(! $cost->isNegative(), "lines.{$i}.unit_cost", 'Cost cannot be negative.');
                BusinessRules::require(! $product->is_batch_tracked || ! empty($line['batch_no']), "lines.{$i}.batch_no", 'This product requires a batch number.');
                BusinessRules::require(empty($line['expiry_date']) || ! empty($line['batch_no']), "lines.{$i}.batch_no", 'An expiry date requires a batch number.');
                $batch = null;
                if (! empty($line['batch_no'])) {
                    $batch = Batch::firstOrCreate(['product_id' => $product->id, 'batch_no' => $line['batch_no']], ['expiry_date' => $line['expiry_date'] ?? null]);
                    if (! empty($line['expiry_date'])) {
                        BusinessRules::require($batch->expiry_date?->format('Y-m-d') === $line['expiry_date'], "lines.{$i}.expiry_date", 'This batch already has a different expiry date. Use the correct batch.');
                    }
                    BusinessRules::require(! $batch->expiry_date || $batch->expiry_date->format('Y-m-d') >= BusinessDate::today() || $location->type === 'quarantine', "lines.{$i}.expiry_date", 'Expired goods can only be received into quarantine.');
                }
                if ($order) {
                    $orderLine = $order->lines()->where('product_id', $product->id)->first();
                    BusinessRules::require($orderLine !== null, "lines.{$i}.product_id", 'This product is not on the order.');
                    $received = $orderLine->received_quantity->plus($quantity);
                    BusinessRules::require($received->isLessThanOrEqualTo($orderLine->quantity), "lines.{$i}.quantity_received", 'Quantity exceeds the unreceived order quantity.');
                    $orderLine->update(['received_quantity' => $received]);
                }
                $lineTotal = Money::of($quantity->toBigDecimal()->multipliedBy($cost->toBigDecimal()));
                $receipt->lines()->create(['product_id' => $product->id, 'batch_id' => $batch?->id, 'quantity_received' => $quantity, 'unit_cost' => $cost, 'line_total' => $lineTotal]);
                StockLedger::postMovement($product->id, $location->id, $batch?->id, $quantity, $cost, StockMovement::GOODS_RECEIPT, 'GoodsReceipt', $receipt->id, $actor);
                $total = $total->plus($lineTotal);
            }
            $receipt->update(['total_amount' => $total]);
            if ($order) {
                $order->update(['status' => $order->lines()->whereColumn('received_quantity', '<', 'quantity')->exists() ? 'partial' : 'received']);
            }
            AuditLog::record($actor, 'GOODS_RECEIPT_POSTED', $receipt, ['total_amount' => (string) $total, 'line_count' => count($input['lines'])]);

            return $receipt->load('lines.product', 'lines.batch', 'supplier', 'location');
        });
    }
}

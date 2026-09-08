<?php

namespace App\Support\Inventory;

use App\Models\Batch;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOperation;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\BusinessRules;
use App\Support\Decimal\Cost;
use App\Support\Decimal\Quantity;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

class InventoryOperationService
{
    public static function post(User $actor, array $input): StockOperation
    {
        return DB::transaction(function () use ($actor, $input) {
            $product = Product::lockForUpdate()->findOrFail($input['product_id']);
            $location = Location::findOrFail($input['location_id']);
            $batchId = $input['batch_id'] ?? null;
            BusinessRules::require(! $product->is_batch_tracked || $batchId !== null, 'batch_id', 'Choose the batch being adjusted.');
            if ($batchId) {
                BusinessRules::require(Batch::whereKey($batchId)->where('product_id', $product->id)->exists(), 'batch_id', 'Batch does not belong to this product.');
            }
            $signed = Quantity::of((string) $input['quantity']);
            $quantity = BusinessRules::quantity($product, (string) $signed->abs());
            $type = $input['type'];
            BusinessRules::require(in_array($type, ['adjustment', 'damage', 'expired', 'transfer']), 'type', 'Unknown stock operation.');
            $destination = $type === 'transfer' ? Location::findOrFail($input['destination_id']) : null;
            if ($destination) {
                BusinessRules::require($destination->id !== $location->id && $signed->isPositive(), 'destination_id', 'Transfer a positive quantity to a different location.');
                BusinessRules::require($location->type !== 'quarantine' || $destination->type === 'quarantine', 'destination_id', 'Quarantined stock must be written off or returned to its supplier.');
            }
            $delta = in_array($type, ['transfer', 'damage', 'expired']) ? $quantity->negated() : $signed;
            $cost = $delta->isPositive() ? Cost::of((string) ($input['unit_cost'] ?? '0')) : null;
            BusinessRules::require(! $delta->isPositive() || isset($input['unit_cost']), 'unit_cost', 'Provide a unit cost for added stock.');
            $operation = StockOperation::create([
                'number' => DocumentNumber::next('STK'), 'type' => $type, 'product_id' => $product->id,
                'location_id' => $location->id, 'destination_id' => $destination?->id, 'batch_id' => $batchId,
                'actor_id' => $actor->id, 'quantity' => $type === 'transfer' ? $quantity : $delta,
                'unit_cost' => Cost::zero(), 'reason' => $input['reason'], 'posted_at' => now(),
            ]);
            $movementType = match ($type) {
                'transfer' => StockMovement::TRANSFER_OUT, 'damage', 'expired' => StockMovement::WRITE_OFF, default => StockMovement::ADJUSTMENT
            };
            $result = StockLedger::postMovement($product->id, $location->id, $batchId, $delta, $cost, $movementType, 'StockOperation', $operation->id, $actor, note: $input['reason']);
            if ($destination) {
                StockLedger::postMovement($product->id, $destination->id, $batchId, $quantity, $result->movement->unit_cost, StockMovement::TRANSFER_IN, 'StockOperation', $operation->id, $actor, note: $input['reason']);
            }
            $operation->update(['unit_cost' => $result->movement->unit_cost]);
            AuditLog::record($actor, 'STOCK_OPERATION_POSTED', $operation);

            return $operation;
        });
    }
}

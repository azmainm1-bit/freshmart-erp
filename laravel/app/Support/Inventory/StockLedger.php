<?php

namespace App\Support\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\Batch;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Decimal\Cost;
use App\Support\Decimal\Quantity;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The single write path for every stock quantity change in the system
 * (docs/ARCHITECTURE.md — ported from the legacy Node app's
 * postStockMovement() with the same invariants, see docs/ARCHITECTURE.md).
 * Must be called inside an existing DB transaction so the stock effect
 * commits atomically with the business document (sale, goods receipt,
 * return, adjustment, transfer) that caused it.
 *
 * Concurrency safety: locks the (product, location, batch) balance row
 * with SELECT ... FOR UPDATE before reading or writing it, so two
 * concurrent sales of the same last unit serialize on this row instead of
 * racing (docs/ARCHITECTURE.md "two cashiers cannot sell the same last unit").
 */
class StockLedger
{
    public static function postMovement(
        string $productId,
        string $locationId,
        ?string $batchId,
        Quantity $quantityDelta,
        ?Cost $unitCost,
        string $movementType,
        string $sourceDocumentType,
        string $sourceDocumentId,
        User $actor,
        bool $allowNegative = false,
        ?string $note = null,
    ): StockMovementResult {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Stock movements require an enclosing business transaction.');
        }
        if ($unitCost?->isNegative()) {
            throw new InvalidArgumentException('Unit cost cannot be negative.');
        }
        if ($batchId !== null && ! Batch::whereKey($batchId)->where('product_id', $productId)->exists()) {
            throw new InvalidArgumentException('Batch does not belong to this product.');
        }
        if ($quantityDelta->isZero()) {
            throw new InvalidArgumentException('postMovement: quantityDelta must not be zero');
        }
        if ($quantityDelta->isPositive() && $unitCost === null) {
            throw new InvalidArgumentException('postMovement: unitCost is required for inbound movements');
        }

        // Ensure the balance row exists (no-op if already there), then
        // lock it. The ON CONFLICT target must match one of the two
        // partial unique indexes depending on whether batch_id is null —
        // see the migration for why a plain composite unique can't be used.
        $conflictTarget = $batchId === null
            ? '(product_id, location_id) WHERE batch_id IS NULL'
            : '(product_id, location_id, batch_id) WHERE batch_id IS NOT NULL';

        DB::statement(
            "insert into stock_balances (id, product_id, location_id, batch_id, quantity_on_hand, average_cost, updated_at)
             values (?, ?, ?, ?, 0, 0, now())
             on conflict {$conflictTarget} do nothing",
            [(string) Str::uuid(), $productId, $locationId, $batchId]
        );

        $locked = DB::selectOne(
            'select id, quantity_on_hand, average_cost from stock_balances
             where product_id = ? and location_id = ? and batch_id is not distinct from ?
             for update',
            [$productId, $locationId, $batchId]
        );

        $currentQty = Quantity::of((string) $locked->quantity_on_hand);
        $currentAvgCost = Cost::of((string) $locked->average_cost);

        $newQty = $currentQty->plus($quantityDelta);
        if ($newQty->isNegative()) {
            throw new InsufficientStockException($productId, $currentQty->toString(), $quantityDelta->abs()->toString());
        }

        if ($quantityDelta->isPositive()) {
            // Inbound: recompute moving weighted-average cost.
            $totalOldValue = $currentQty->toBigDecimal()->multipliedBy($currentAvgCost->toBigDecimal());
            $totalNewValue = $quantityDelta->toBigDecimal()->multipliedBy($unitCost->toBigDecimal());
            $newAvgCost = $newQty->isZero()
                ? $unitCost
                : Cost::of($totalOldValue->plus($totalNewValue)->dividedBy($newQty->toBigDecimal(), 6, RoundingMode::HalfUp));
            $movementUnitCost = $unitCost;
        } else {
            // Outbound: cost basis is the current average cost; average
            // cost itself does not change on an outbound movement.
            $newAvgCost = $currentAvgCost;
            $movementUnitCost = $currentAvgCost;
        }

        DB::statement(
            'update stock_balances set quantity_on_hand = ?, average_cost = ?, updated_at = now() where id = ?',
            [$newQty->toString(), $newAvgCost->toString(), $locked->id]
        );

        $movement = StockMovement::create([
            'product_id' => $productId,
            'location_id' => $locationId,
            'batch_id' => $batchId,
            'quantity_delta' => $quantityDelta,
            'unit_cost' => $movementUnitCost,
            'movement_type' => $movementType,
            'source_document_type' => $sourceDocumentType,
            'source_document_id' => $sourceDocumentId,
            'actor_id' => $actor->id,
            'note' => $note,
            'created_at' => now(),
        ]);

        $balance = StockBalance::findOrFail($locked->id);

        return new StockMovementResult($movement, $balance);
    }
}

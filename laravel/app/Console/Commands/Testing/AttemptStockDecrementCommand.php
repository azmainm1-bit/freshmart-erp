<?php

namespace App\Console\Commands\Testing;

use App\Exceptions\InsufficientStockException;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Decimal\Quantity;
use App\Support\Inventory\StockLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TEST INFRASTRUCTURE ONLY — refuses to run outside local/testing. Exists
 * so tests/Feature/Inventory/StockLedgerConcurrencyTest.php can prove
 * App\Support\Inventory\StockLedger's row-locking is safe under genuine
 * OS-level concurrency (two real separate PHP processes racing for the
 * same unit of stock), the same way a real POS checkout endpoint will
 * call it in Migration-Phase 3 — see docs/ARCHITECTURE.md "two cashiers cannot
 * sell the same last unit." A raw-HTTP version of this same proof will be
 * added once the checkout endpoint exists; this proves the underlying
 * ledger primitive today, ahead of that.
 */
class AttemptStockDecrementCommand extends Command
{
    protected $signature = 'testing:attempt-stock-decrement
        {product : Product UUID}
        {location : Location UUID}
        {actor : User UUID performing the decrement}
        {quantity : Quantity to decrement, e.g. 1}';

    protected $description = 'Test-only: attempt a single stock decrement and report success/failure via exit code';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        $actor = User::findOrFail($this->argument('actor'));

        try {
            DB::transaction(function () use ($actor) {
                StockLedger::postMovement(
                    productId: $this->argument('product'),
                    locationId: $this->argument('location'),
                    batchId: null,
                    quantityDelta: Quantity::of($this->argument('quantity'))->negated(),
                    unitCost: null,
                    movementType: StockMovement::SALE,
                    sourceDocumentType: 'TestRace',
                    sourceDocumentId: (string) Str::uuid(),
                    actor: $actor,
                    allowNegative: false,
                );
            });
            $this->line('SUCCESS');

            return self::SUCCESS;
        } catch (InsufficientStockException $e) {
            $this->line('INSUFFICIENT_STOCK: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

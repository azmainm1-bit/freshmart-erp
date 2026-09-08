<?php

namespace Tests\Feature\Inventory;

use App\Models\Location;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Decimal\Cost;
use App\Support\Decimal\Quantity;
use App\Support\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Deliberately does NOT use RefreshDatabase — this test spawns real,
 * separate `php artisan` OS processes (via Symfony Process) to race for
 * the same unit of stock, and those processes need to see genuinely
 * committed setup data over their own database connections. This is the
 * most direct proof available before a real HTTP checkout endpoint exists
 * (Migration-Phase 3): two independent processes, each running the exact
 * StockLedger::postMovement() code path a real sale would use, racing for
 * the literal last unit — docs/ARCHITECTURE.md
 */
class StockLedgerConcurrencyTest extends TestCase
{
    private ?Product $product = null;

    private ?Location $location = null;

    private ?User $actor = null;

    protected function tearDown(): void
    {
        if ($this->product) {
            DB::table('stock_movements')->where('product_id', $this->product->id)->delete();
            DB::table('stock_balances')->where('product_id', $this->product->id)->delete();
            $this->product->delete();
        }
        $this->location?->delete();
        $this->actor?->delete();

        parent::tearDown();
    }

    public function test_two_processes_racing_for_the_last_unit_exactly_one_succeeds(): void
    {
        $this->actor = User::factory()->create();
        $this->location = Location::create(['name' => 'Race Floor', 'type' => 'sales_floor']);
        $this->product = Product::create([
            'sku' => 'RACE-'.uniqid(),
            'name' => 'Race Test Item',
            'category' => 'Test',
            'stock_unit' => 'unit',
            'purchase_unit' => 'unit',
            'selling_price' => '100.00',
        ]);

        // Seed exactly 1 unit of stock, committed for real.
        DB::transaction(function () {
            StockLedger::postMovement(
                productId: $this->product->id,
                locationId: $this->location->id,
                batchId: null,
                quantityDelta: Quantity::of('1'),
                unitCost: Cost::of('50'),
                movementType: StockMovement::OPENING_BALANCE,
                sourceDocumentType: 'Test',
                sourceDocumentId: (string) Str::uuid(),
                actor: $this->actor,
                allowNegative: true,
            );
        });

        $env = [
            'DB_CONNECTION' => config('database.default'),
            'DB_HOST' => config('database.connections.pgsql.host'),
            'DB_PORT' => config('database.connections.pgsql.port'),
            'DB_DATABASE' => config('database.connections.pgsql.database'),
            'DB_USERNAME' => config('database.connections.pgsql.username'),
            'DB_PASSWORD' => config('database.connections.pgsql.password'),
        ];

        $command = [
            'php', 'artisan', 'testing:attempt-stock-decrement',
            $this->product->id, $this->location->id, $this->actor->id, '1',
        ];

        $processA = Process::env($env)->path(base_path())->start($command);
        $processB = Process::env($env)->path(base_path())->start($command);

        $resultA = $processA->wait();
        $resultB = $processB->wait();

        $outputs = [$resultA->output().$resultA->errorOutput(), $resultB->output().$resultB->errorOutput()];
        $successes = collect($outputs)->filter(fn ($o) => str_contains($o, 'SUCCESS'))->count();
        $failures = collect($outputs)->filter(fn ($o) => str_contains($o, 'INSUFFICIENT_STOCK'))->count();

        $this->assertSame(1, $successes, 'exactly one of the two concurrent sales must succeed. Outputs: '.implode(' | ', $outputs));
        $this->assertSame(1, $failures, 'the other must be cleanly rejected as insufficient stock. Outputs: '.implode(' | ', $outputs));

        $balance = StockBalance::where('product_id', $this->product->id)->where('location_id', $this->location->id)->first();
        $this->assertSame('0.000', $balance->quantity_on_hand->toString(), 'final stock must be exactly 0, never negative, never double-sold');
    }
}

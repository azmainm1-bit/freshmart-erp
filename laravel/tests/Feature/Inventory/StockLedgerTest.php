<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\Batch;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Decimal\Cost;
use App\Support\Decimal\Quantity;
use App\Support\Inventory\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::create([
            'sku' => 'SKU-'.uniqid(),
            'name' => 'Test product',
            'category' => 'Test',
            'stock_unit' => 'unit',
            'purchase_unit' => 'unit',
            'selling_price' => '10.00',
        ]);
    }

    private function location(): Location
    {
        return Location::create(['name' => 'Test Location', 'type' => 'sales_floor']);
    }

    public function test_first_inbound_movement_sets_average_cost_to_the_received_cost(): void
    {
        $actor = User::factory()->create();
        $product = $this->product();
        $location = $this->location();

        $result = StockLedger::postMovement(
            $product->id, $location->id, null,
            Quantity::of('10'), Cost::of('150.00'),
            StockMovement::GOODS_RECEIPT, 'Test', (string) Str::uuid(), $actor,
        );

        $this->assertSame('10.000', $result->balance->quantity_on_hand->toString());
        $this->assertSame('150.000000', $result->balance->average_cost->toString());
    }

    public function test_second_inbound_movement_blends_into_a_weighted_average_cost(): void
    {
        $actor = User::factory()->create();
        $product = $this->product();
        $location = $this->location();

        StockLedger::postMovement($product->id, $location->id, null, Quantity::of('10'), Cost::of('100.00'), StockMovement::GOODS_RECEIPT, 'Test', (string) Str::uuid(), $actor);
        $result = StockLedger::postMovement($product->id, $location->id, null, Quantity::of('10'), Cost::of('200.00'), StockMovement::GOODS_RECEIPT, 'Test', (string) Str::uuid(), $actor);

        // (10*100 + 10*200) / 20 = 150
        $this->assertSame('20.000', $result->balance->quantity_on_hand->toString());
        $this->assertSame('150.000000', $result->balance->average_cost->toString());
    }

    public function test_outbound_movement_does_not_change_average_cost(): void
    {
        $actor = User::factory()->create();
        $product = $this->product();
        $location = $this->location();

        StockLedger::postMovement($product->id, $location->id, null, Quantity::of('10'), Cost::of('100.00'), StockMovement::GOODS_RECEIPT, 'Test', (string) Str::uuid(), $actor);
        $result = StockLedger::postMovement($product->id, $location->id, null, Quantity::of('-3'), null, StockMovement::SALE, 'Test', (string) Str::uuid(), $actor);

        $this->assertSame('7.000', $result->balance->quantity_on_hand->toString());
        $this->assertSame('100.000000', $result->balance->average_cost->toString());
        // The movement's own unit_cost snapshot is the pre-movement average — used for margin reporting.
        $this->assertSame('100.000000', $result->movement->unit_cost->toString());
    }

    public function test_outbound_movement_exceeding_stock_is_rejected_and_leaves_balance_unchanged(): void
    {
        $actor = User::factory()->create();
        $product = $this->product();
        $location = $this->location();

        StockLedger::postMovement($product->id, $location->id, null, Quantity::of('5'), Cost::of('100.00'), StockMovement::GOODS_RECEIPT, 'Test', (string) Str::uuid(), $actor);

        $this->expectException(InsufficientStockException::class);
        try {
            StockLedger::postMovement($product->id, $location->id, null, Quantity::of('-6'), null, StockMovement::SALE, 'Test', (string) Str::uuid(), $actor);
        } finally {
            $balance = StockBalance::where('product_id', $product->id)->first();
            $this->assertSame('5.000', $balance->quantity_on_hand->toString(), 'a rejected sale must not touch the balance');
        }
    }

    public function test_legacy_allow_negative_flag_cannot_bypass_stock_integrity(): void
    {
        $actor = User::factory()->create();
        $product = $this->product();
        $location = $this->location();

        $this->expectException(InsufficientStockException::class);
        StockLedger::postMovement($product->id, $location->id, null, Quantity::of('-2'), null, StockMovement::ADJUSTMENT, 'Test', (string) Str::uuid(), $actor, allowNegative: true);
    }

    public function test_non_batch_tracked_products_do_not_create_duplicate_balance_rows(): void
    {
        // Regression test for the exact bug the legacy Node app found and
        // fixed (docs/ARCHITECTURE.md, migration 20260907135108): a plain
        // composite unique index allows duplicates when batch_id IS NULL
        // because Postgres treats NULL as distinct from NULL.
        $actor = User::factory()->create();
        $product = $this->product();
        $location = $this->location();

        StockLedger::postMovement($product->id, $location->id, null, Quantity::of('1'), Cost::of('10'), StockMovement::GOODS_RECEIPT, 'Test', (string) Str::uuid(), $actor);
        StockLedger::postMovement($product->id, $location->id, null, Quantity::of('1'), Cost::of('10'), StockMovement::GOODS_RECEIPT, 'Test', (string) Str::uuid(), $actor);
        StockLedger::postMovement($product->id, $location->id, null, Quantity::of('1'), Cost::of('10'), StockMovement::GOODS_RECEIPT, 'Test', (string) Str::uuid(), $actor);

        $count = StockBalance::where('product_id', $product->id)->where('location_id', $location->id)->count();
        $this->assertSame(1, $count, 'exactly one balance row, not one per movement');

        $balance = StockBalance::where('product_id', $product->id)->first();
        $this->assertSame('3.000', $balance->quantity_on_hand->toString());
    }

    public function test_batch_tracked_products_get_separate_balance_rows_per_batch(): void
    {
        $actor = User::factory()->create();
        $product = $this->product();
        $location = $this->location();
        $batchA = Batch::create(['product_id' => $product->id, 'batch_no' => 'A']);
        $batchB = Batch::create(['product_id' => $product->id, 'batch_no' => 'B']);

        StockLedger::postMovement($product->id, $location->id, $batchA->id, Quantity::of('5'), Cost::of('10'), StockMovement::GOODS_RECEIPT, 'Test', (string) Str::uuid(), $actor);
        StockLedger::postMovement($product->id, $location->id, $batchB->id, Quantity::of('7'), Cost::of('12'), StockMovement::GOODS_RECEIPT, 'Test', (string) Str::uuid(), $actor);

        $count = StockBalance::where('product_id', $product->id)->count();
        $this->assertSame(2, $count);
    }

    public function test_every_movement_is_attributable(): void
    {
        $actor = User::factory()->create();
        $product = $this->product();
        $location = $this->location();
        $sourceId = (string) Str::uuid();

        $result = StockLedger::postMovement($product->id, $location->id, null, Quantity::of('1'), Cost::of('10'), StockMovement::GOODS_RECEIPT, 'GoodsReceipt', $sourceId, $actor, note: 'test note');

        $this->assertSame($actor->id, $result->movement->actor_id);
        $this->assertSame('GoodsReceipt', $result->movement->source_document_type);
        $this->assertSame($sourceId, $result->movement->source_document_id);
        $this->assertSame($product->id, $result->movement->product_id);
        $this->assertSame($location->id, $result->movement->location_id);
        $this->assertSame('test note', $result->movement->note);
        $this->assertNotNull($result->movement->created_at);
    }
}

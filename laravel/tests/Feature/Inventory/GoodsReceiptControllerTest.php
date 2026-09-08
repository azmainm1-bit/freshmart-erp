<?php

namespace Tests\Feature\Inventory;

use App\Models\Location;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoodsReceiptControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function manager(): User
    {
        $u = User::factory()->create();
        $u->assignRole('manager');

        return $u;
    }

    private function cashier(): User
    {
        $u = User::factory()->create();
        $u->assignRole('cashier');

        return $u;
    }

    private function fixtures(): array
    {
        return [
            Supplier::create(['name' => 'Test Supplier']),
            Location::create(['name' => 'Stockroom', 'type' => 'stockroom']),
            Product::create([
                'sku' => 'GR-'.uniqid(), 'name' => 'Oil', 'category' => 'Grocery',
                'stock_unit' => 'bottle', 'purchase_unit' => 'carton', 'selling_price' => '180.00',
            ]),
        ];
    }

    public function test_requires_idempotency_key_header(): void
    {
        [$supplier, $location, $product] = $this->fixtures();

        $this->actingAs($this->manager())
            ->postJson('/api/goods-receipts', [
                'supplier_id' => $supplier->id,
                'location_id' => $location->id,
                'lines' => [['product_id' => $product->id, 'quantity_received' => '10', 'unit_cost' => '150.00']],
            ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'IDEMPOTENCY_KEY_REQUIRED');
    }

    public function test_a_cashier_without_the_receive_permission_is_rejected(): void
    {
        [$supplier, $location, $product] = $this->fixtures();

        $this->actingAs($this->cashier())
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/goods-receipts', [
                'supplier_id' => $supplier->id,
                'location_id' => $location->id,
                'lines' => [['product_id' => $product->id, 'quantity_received' => '10', 'unit_cost' => '150.00']],
            ])
            ->assertForbidden();
    }

    public function test_a_manager_can_post_a_goods_receipt_and_stock_increases(): void
    {
        [$supplier, $location, $product] = $this->fixtures();

        $response = $this->actingAs($this->manager())
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/goods-receipts', [
                'supplier_id' => $supplier->id,
                'location_id' => $location->id,
                'lines' => [['product_id' => $product->id, 'quantity_received' => '10', 'unit_cost' => '150.00']],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('replayed', false);

        $balance = StockBalance::where('product_id', $product->id)->first();
        $this->assertSame('10.000', $balance->quantity_on_hand->toString());
    }

    public function test_retrying_with_the_same_idempotency_key_does_not_double_the_stock(): void
    {
        [$supplier, $location, $product] = $this->fixtures();
        $key = (string) Str::uuid();
        $payload = [
            'supplier_id' => $supplier->id,
            'location_id' => $location->id,
            'lines' => [['product_id' => $product->id, 'quantity_received' => '10', 'unit_cost' => '150.00']],
        ];
        $manager = $this->manager();

        $first = $this->actingAs($manager)->withHeaders(['Idempotency-Key' => $key])->postJson('/api/goods-receipts', $payload);
        $second = $this->actingAs($manager)->withHeaders(['Idempotency-Key' => $key])->postJson('/api/goods-receipts', $payload);

        $first->assertStatus(201)->assertJsonPath('replayed', false);
        $second->assertStatus(201)->assertJsonPath('replayed', true);

        $balance = StockBalance::where('product_id', $product->id)->first();
        $this->assertSame('10.000', $balance->quantity_on_hand->toString(), 'must not be 20 — the retry replayed, it did not re-execute');
    }

    public function test_validation_rejects_a_missing_product(): void
    {
        [$supplier, $location] = $this->fixtures();

        $this->actingAs($this->manager())
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/goods-receipts', [
                'supplier_id' => $supplier->id,
                'location_id' => $location->id,
                'lines' => [['product_id' => (string) Str::uuid(), 'quantity_received' => '10', 'unit_cost' => '150.00']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.product_id']);
    }
}

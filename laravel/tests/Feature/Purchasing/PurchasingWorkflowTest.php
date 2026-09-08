<?php

namespace Tests\Feature\Purchasing;

use App\Models\Batch;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Purchasing\PurchaseOrderService;
use App\Support\Purchasing\ReceivingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchasingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Supplier $supplier;

    private Location $location;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
        $this->supplier = Supplier::create(['name' => 'Test distributor']);
        $this->location = Location::create(['name' => 'Stockroom', 'type' => 'stockroom']);
        $this->product = Product::factory()->create();
        $this->actingAs($this->manager);
    }

    private function receipt(array $line = []): GoodsReceipt
    {
        return ReceivingService::post($this->manager, [
            'supplier_id' => $this->supplier->id, 'location_id' => $this->location->id,
            'lines' => [['product_id' => $this->product->id, 'quantity_received' => '10', 'unit_cost' => '25', ...$line]],
        ]);
    }

    private function postOperation(string $url, array $data, ?string $key = null)
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson($url, $data);
    }

    public function test_order_partial_receiving_and_over_receiving_are_consistent(): void
    {
        $order = PurchaseOrderService::create($this->manager, ['supplier_id' => $this->supplier->id, 'location_id' => $this->location->id, 'lines' => [['product_id' => $this->product->id, 'quantity' => '10', 'unit_cost' => '25']]]);
        $payload = ['supplier_id' => $this->supplier->id, 'location_id' => $this->location->id, 'purchase_order_id' => $order->id, 'lines' => [['product_id' => $this->product->id, 'quantity_received' => '6', 'unit_cost' => '25']]];
        $this->postOperation('/api/goods-receipts', $payload)->assertCreated()->assertJsonPath('goods_receipt.total_amount', '150.00');
        $this->assertSame('partial', $order->fresh()->status);
        $this->postOperation('/api/goods-receipts', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('goods_receipts', 1);
        $payload['lines'][0]['quantity_received'] = '4';
        $this->postOperation('/api/goods-receipts', $payload)->assertCreated();
        $this->assertSame('received', $order->fresh()->status);
        $this->assertSame('10.000', (string) StockBalance::first()->quantity_on_hand);
    }

    public function test_receiving_rejects_batch_expiry_changes_without_partial_effects(): void
    {
        $this->product->update(['is_batch_tracked' => true]);
        $date = now()->addMonth()->format('Y-m-d');
        $this->receipt(['batch_no' => 'LOT-001', 'expiry_date' => $date]);
        $this->postOperation('/api/goods-receipts', ['supplier_id' => $this->supplier->id, 'location_id' => $this->location->id, 'lines' => [['product_id' => $this->product->id, 'quantity_received' => '5', 'unit_cost' => '25', 'batch_no' => 'LOT-001', 'expiry_date' => now()->addYear()->format('Y-m-d')]]])->assertUnprocessable();
        $this->assertSame($date, Batch::first()->expiry_date->format('Y-m-d'));
        $this->assertDatabaseCount('goods_receipts', 1);
        $this->assertSame('10.000', (string) StockBalance::first()->quantity_on_hand);
    }

    public function test_batch_tracked_receiving_requires_a_batch_and_units_cannot_be_fractional(): void
    {
        $this->product->update(['is_batch_tracked' => true]);
        $payload = ['supplier_id' => $this->supplier->id, 'location_id' => $this->location->id, 'lines' => [['product_id' => $this->product->id, 'quantity_received' => '1', 'unit_cost' => '25']]];
        $this->postOperation('/api/goods-receipts', $payload)->assertJsonValidationErrors('lines.0.batch_no');
        $payload['lines'][0]['batch_no'] = 'LOT-1';
        $payload['lines'][0]['quantity_received'] = '0.5';
        $this->postOperation('/api/goods-receipts', $payload)->assertJsonValidationErrors('lines.0.quantity_received');
        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_supplier_payment_is_idempotent_and_cannot_overpay(): void
    {
        $receipt = $this->receipt();
        $url = "/api/goods-receipts/{$receipt->id}/payments";
        $key = (string) Str::uuid();
        $this->postOperation($url, ['amount' => '100', 'method' => 'cash'], $key)->assertCreated();
        $this->postOperation($url, ['amount' => '100', 'method' => 'cash'], $key)->assertCreated()->assertJsonPath('replayed', true);
        $this->postOperation($url, ['amount' => '151', 'method' => 'cash'])->assertUnprocessable();
        $this->assertSame('100.00', (string) $receipt->fresh()->paid_amount);
        $this->assertDatabaseCount('supplier_payments', 1);
    }

    public function test_repeated_partial_purchase_returns_reconcile_to_original_line_total(): void
    {
        $receipt = $this->receipt(['quantity_received' => '3', 'unit_cost' => '0.333333']);
        $payload = ['reason' => 'Supplier recall', 'lines' => [['goods_receipt_line_id' => $receipt->lines->first()->id, 'quantity' => '1']]];
        $url = "/api/goods-receipts/{$receipt->id}/returns";
        foreach (['0.33', '0.34', '0.33'] as $amount) {
            $this->postOperation($url, $payload)->assertCreated()->assertJsonPath('return.total_amount', $amount);
        }
        $this->postOperation($url, $payload)->assertUnprocessable();
        $this->assertSame('1.00', (string) $receipt->fresh()->returned_amount);
        $this->assertSame('0.000', (string) StockBalance::first()->quantity_on_hand);
    }

    public function test_transfer_preserves_quantity_and_cost_and_failure_rolls_back(): void
    {
        $this->receipt();
        $floor = Location::create(['name' => 'Sales floor', 'type' => 'sales_floor']);
        $payload = ['type' => 'transfer', 'product_id' => $this->product->id, 'location_id' => $this->location->id, 'destination_id' => $floor->id, 'quantity' => '4', 'reason' => 'Replenish shelf'];
        $this->postOperation('/api/stock-operations', $payload)->assertCreated();
        $this->assertSame('6.000', (string) StockBalance::where('location_id', $this->location->id)->first()->quantity_on_hand);
        $destination = StockBalance::where('location_id', $floor->id)->first();
        $this->assertSame('4.000', (string) $destination->quantity_on_hand);
        $this->assertSame('25.000000', (string) $destination->average_cost);
        $payload['quantity'] = '7';
        $this->postOperation('/api/stock-operations', $payload)->assertConflict();
        $this->assertDatabaseCount('stock_operations', 1);
        $this->assertSame('4.000', (string) $destination->fresh()->quantity_on_hand);
    }

    public function test_write_off_deducts_stock_and_requires_a_reason(): void
    {
        $this->receipt();
        $payload = ['type' => 'damage', 'product_id' => $this->product->id, 'location_id' => $this->location->id, 'quantity' => '2'];
        $this->postOperation('/api/stock-operations', $payload)->assertJsonValidationErrors('reason');
        $this->postOperation('/api/stock-operations', [...$payload, 'reason' => 'Broken packaging'])->assertCreated();
        $this->assertSame('8.000', (string) StockBalance::first()->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'write_off', 'quantity_delta' => '-2']);
    }

    public function test_cashier_and_inventory_staff_cannot_record_supplier_payments(): void
    {
        $receipt = $this->receipt();
        foreach (['cashier', 'inventory'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user);
            $this->postOperation("/api/goods-receipts/{$receipt->id}/payments", ['amount' => '20', 'method' => 'cash'])->assertForbidden();
        }
        $this->assertDatabaseCount('supplier_payments', 0);
    }
}

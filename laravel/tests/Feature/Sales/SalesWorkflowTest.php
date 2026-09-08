<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Purchasing\ReceivingService;
use App\Support\Sales\ShiftService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Location $floor;

    private Product $product;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
        $this->floor = Location::create(['name' => 'Sales floor', 'type' => 'sales_floor']);
        $this->product = Product::factory()->create(['selling_price' => '100', 'tax_rate_percent' => '10', 'tax_inclusive' => true]);
        $supplier = Supplier::create(['name' => 'Wholesale partner']);
        ReceivingService::post($this->manager, ['supplier_id' => $supplier->id, 'location_id' => $this->floor->id, 'lines' => [['product_id' => $this->product->id, 'quantity_received' => '20', 'unit_cost' => '40']]]);
        $this->shift = ShiftService::open($this->manager, ['location_id' => $this->floor->id, 'counter' => 'Till 1', 'opening_cash' => '500']);
        $this->actingAs($this->manager);
    }

    private function postOperation(string $url, array $data, ?string $key = null)
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson($url, $data);
    }

    private function payload(array $override = []): array
    {
        return ['shift_id' => $this->shift->id, 'lines' => [['product_id' => $this->product->id, 'quantity' => '2']], 'payments' => [['method' => 'cash', 'amount' => '250']], ...$override];
    }

    public function test_checkout_tax_change_stock_and_shift_cash_reconcile(): void
    {
        $response = $this->postOperation('/api/sales', $this->payload())->assertCreated();
        $response->assertJsonPath('sale.grand_total', '200.00')->assertJsonPath('sale.subtotal', '181.82')->assertJsonPath('sale.tax_total', '18.18')->assertJsonPath('sale.change_amount', '50.00')->assertJsonPath('sale.payments.0.amount', '200.00');
        $this->assertSame('18.000', (string) StockBalance::first()->quantity_on_hand);
        $this->assertSame('80.00', (string) Sale::first()->cost_total);
        $this->assertSame('700.00', (string) ShiftService::expectedCash($this->shift));
        $response->assertJsonMissingPath('sale.cost_total')->assertJsonMissingPath('sale.lines.0.cost_total');
        $this->postOperation("/api/shifts/{$this->shift->id}/close", ['counted_cash' => '700'])->assertCreated()->assertJsonPath('shift.variance', '0.00');
        $this->postOperation('/api/sales', $this->payload())->assertUnprocessable();
    }

    public function test_sale_retry_replays_but_different_payload_conflicts(): void
    {
        $key = (string) Str::uuid();
        $payload = $this->payload();
        $id = $this->postOperation('/api/sales', $payload, $key)->assertCreated()->json('sale.id');
        $this->postOperation('/api/sales', $payload, $key)->assertCreated()->assertJsonPath('sale.id', $id)->assertJsonPath('replayed', true);
        $payload['lines'][0]['quantity'] = '1';
        $this->postOperation('/api/sales', $payload, $key)->assertConflict();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sales_payments', 1);
    }

    public function test_split_payment_rejects_noncash_overpayment_and_missing_references(): void
    {
        $this->postOperation('/api/sales', $this->payload(['payments' => [['method' => 'card', 'amount' => '250', 'reference' => 'CARD-100']]]))->assertUnprocessable();
        $this->postOperation('/api/sales', $this->payload(['payments' => [['method' => 'card', 'amount' => '200']]]))->assertUnprocessable();
        $this->assertDatabaseCount('sales', 0);
        $this->postOperation('/api/sales', $this->payload(['payments' => [['method' => 'mobile', 'amount' => '100', 'reference' => 'MOBILE-101'], ['method' => 'cash', 'amount' => '150']]]))->assertCreated()->assertJsonPath('sale.change_amount', '50.00');
        $this->assertSame('600.00', (string) ShiftService::expectedCash($this->shift));
    }

    public function test_failed_stock_deduction_rolls_back_entire_sale(): void
    {
        $payload = $this->payload();
        $payload['lines'][0]['quantity'] = '21';
        $payload['payments'][0]['amount'] = '2100';
        $this->postOperation('/api/sales', $payload)->assertConflict();
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_lines', 0);
        $this->assertDatabaseCount('sales_payments', 0);
        $this->assertSame('20.000', (string) StockBalance::first()->quantity_on_hand);
    }

    public function test_credit_sale_limit_collection_and_return_only_refund_collected_money(): void
    {
        $customer = Customer::create(['name' => 'Credit shopper', 'credit_limit' => '150']);
        $payload = $this->payload(['customer_id' => $customer->id, 'payments' => [['method' => 'cash', 'amount' => '50']]]);
        $id = $this->postOperation('/api/sales', $payload)->assertCreated()->json('sale.id');
        $this->postOperation('/api/sales', $payload)->assertJsonValidationErrors('payments');
        $this->postOperation("/api/sales/{$id}/payments", ['amount' => '25', 'method' => 'bank', 'reference' => 'BANK-1'])->assertCreated();
        $sale = Sale::findOrFail($id);
        $line = $sale->lines()->first();
        $return = ['location_id' => $this->floor->id, 'shift_id' => $this->shift->id, 'refund_method' => 'cash', 'disposition' => 'restock', 'reason' => 'Customer changed mind', 'lines' => [['sale_line_id' => $line->id, 'quantity' => '1']]];
        $this->postOperation("/api/sales/{$id}/returns", $return)->assertCreated()->assertJsonPath('return.refund_amount', '0.00');
        $this->postOperation("/api/sales/{$id}/returns", $return)->assertCreated()->assertJsonPath('return.refund_amount', '75.00');
        $sale->refresh();
        $this->assertTrue($sale->grand_total->minus($sale->returned_amount)->minus($sale->paid_amount)->plus($sale->refunded_amount)->isZero());
        $this->postOperation("/api/sales/{$id}/payments", ['amount' => '1', 'method' => 'bank', 'reference' => 'BANK-2'])->assertUnprocessable();
    }

    public function test_discounted_partial_returns_sum_to_exact_original_total(): void
    {
        $this->product->update(['selling_price' => '1', 'tax_rate_percent' => '0']);
        $payload = $this->payload(['notes' => 'Clearance discount', 'lines' => [['product_id' => $this->product->id, 'quantity' => '3', 'discount_amount' => '2']], 'payments' => [['method' => 'cash', 'amount' => '1']]]);
        $id = $this->postOperation('/api/sales', $payload)->assertCreated()->json('sale.id');
        $line = Sale::findOrFail($id)->lines()->first();
        $return = ['location_id' => $this->floor->id, 'shift_id' => $this->shift->id, 'refund_method' => 'cash', 'disposition' => 'restock', 'reason' => 'Changed mind', 'lines' => [['sale_line_id' => $line->id, 'quantity' => '1']]];
        foreach (['0.33', '0.34', '0.33'] as $amount) {
            $this->postOperation("/api/sales/{$id}/returns", $return)->assertCreated()->assertJsonPath('return.refund_amount', $amount);
        }
        $this->postOperation("/api/sales/{$id}/returns", $return)->assertUnprocessable();
        $this->assertSame('20.000', (string) StockBalance::first()->quantity_on_hand);
        $this->assertSame('500.00', (string) ShiftService::expectedCash($this->shift));
    }

    public function test_expired_stock_is_not_sold_and_fefo_uses_earliest_batch(): void
    {
        $product = Product::factory()->create(['selling_price' => '10', 'is_batch_tracked' => true]);
        $supplier = Supplier::first();
        foreach (['EARLY' => 5, 'LATE' => 20] as $batch => $days) {
            ReceivingService::post($this->manager, ['supplier_id' => $supplier->id, 'location_id' => $this->floor->id, 'lines' => [['product_id' => $product->id, 'quantity_received' => '1', 'unit_cost' => '5', 'batch_no' => $batch, 'expiry_date' => now()->addDays($days)->format('Y-m-d')]]]);
        }
        $payload = $this->payload(['lines' => [['product_id' => $product->id, 'quantity' => '1']], 'payments' => [['method' => 'cash', 'amount' => '10']]]);
        $id = $this->postOperation('/api/sales', $payload)->assertCreated()->json('sale.id');
        $this->assertSame('EARLY', Sale::findOrFail($id)->lines()->first()->allocations()->first()->batch->batch_no);
        $this->travel(30)->days();
        $this->postOperation('/api/sales', $payload)->assertConflict();
    }

    public function test_cashier_cannot_use_another_till_or_authorize_discounts_and_returns(): void
    {
        $id = $this->postOperation('/api/sales', $this->payload())->assertCreated()->json('sale.id');
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);
        $this->postOperation('/api/sales', $this->payload())->assertForbidden();
        $this->postOperation("/api/sales/{$id}/returns", [])->assertForbidden();
        $shift = ShiftService::open($cashier, ['location_id' => $this->floor->id, 'counter' => 'Till 2', 'opening_cash' => '100']);
        $payload = $this->payload(['shift_id' => $shift->id, 'notes' => 'Unauthorized discount']);
        $payload['lines'][0]['discount_amount'] = '1';
        $this->postOperation('/api/sales', $payload)->assertJsonValidationErrors('lines.0.discount_amount');
    }

    public function test_price_changes_and_duplicate_product_lines_are_rejected(): void
    {
        $this->postOperation('/api/sales', $this->payload(['expected_total' => '199']))->assertJsonValidationErrors('expected_total');
        $payload = $this->payload();
        $payload['lines'][] = $payload['lines'][0];
        $this->postOperation('/api/sales', $payload)->assertJsonValidationErrors('lines.0.product_id');
        $this->assertDatabaseCount('sales', 0);
    }
}

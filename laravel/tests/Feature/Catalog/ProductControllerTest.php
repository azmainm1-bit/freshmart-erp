<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
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

    public function test_a_cashier_cannot_manage_products_via_direct_http(): void
    {
        $this->actingAs($this->cashier())->get('/catalog/products')->assertForbidden();
        $this->actingAs($this->cashier())->post('/catalog/products', [
            'sku' => 'X', 'name' => 'X', 'category' => 'X', 'stock_unit' => 'unit',
            'purchase_unit' => 'unit', 'pack_conversion_factor' => '1', 'selling_price' => '10',
            'tax_rate_percent' => '0', 'tax_inclusive' => true, 'is_weighted' => false,
            'is_batch_tracked' => false, 'reorder_point' => '0',
        ])->assertForbidden();
        $this->assertDatabaseMissing('products', ['sku' => 'X']);
    }

    public function test_a_manager_can_create_a_product_with_barcodes(): void
    {
        $this->actingAs($this->manager())->post('/catalog/products', [
            'sku' => 'OIL-1L', 'name' => 'Cooking Oil 1L', 'name_bn' => 'রান্নার তেল',
            'category' => 'Grocery', 'stock_unit' => 'bottle', 'purchase_unit' => 'carton',
            'pack_conversion_factor' => '12', 'selling_price' => '180.00', 'tax_rate_percent' => '0',
            'tax_inclusive' => true, 'is_weighted' => false, 'is_batch_tracked' => false,
            'reorder_point' => '5', 'barcodes' => ['8901030875021'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $product = Product::where('sku', 'OIL-1L')->firstOrFail();
        $this->assertSame('রান্নার তেল', $product->name_bn);
        $this->assertDatabaseHas('barcodes', ['product_id' => $product->id, 'code' => '8901030875021']);
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        Product::create([
            'sku' => 'DUP', 'name' => 'X', 'category' => 'X',
            'stock_unit' => 'unit', 'purchase_unit' => 'unit', 'selling_price' => '10',
        ]);

        $this->actingAs($this->manager())->post('/catalog/products', [
            'sku' => 'DUP', 'name' => 'Y', 'category' => 'Y', 'stock_unit' => 'unit',
            'purchase_unit' => 'unit', 'pack_conversion_factor' => '1', 'selling_price' => '10',
            'tax_rate_percent' => '0', 'tax_inclusive' => true, 'is_weighted' => false,
            'is_batch_tracked' => false, 'reorder_point' => '0',
        ])->assertSessionHasErrors('sku');
    }

    public function test_search_finds_by_name_sku_and_barcode(): void
    {
        $p = Product::create(['sku' => 'FIND-ME', 'name' => 'Findable Widget', 'category' => 'X', 'stock_unit' => 'unit', 'purchase_unit' => 'unit', 'selling_price' => '10']);
        $p->barcodes()->create(['code' => '1234567890123']);
        Product::create(['sku' => 'OTHER', 'name' => 'Something else', 'category' => 'X', 'stock_unit' => 'unit', 'purchase_unit' => 'unit', 'selling_price' => '10']);

        $bySku = $this->actingAs($this->manager())->get('/catalog/products?q=FIND-ME');
        $bySku->assertInertia(fn ($page) => $page->where('products.data.0.sku', 'FIND-ME')->where('products.data', fn ($data) => count($data) === 1));

        $byBarcode = $this->actingAs($this->manager())->get('/catalog/products?q=1234567890123');
        $byBarcode->assertInertia(fn ($page) => $page->where('products.data.0.sku', 'FIND-ME'));
    }

    public function test_archived_products_are_excluded_from_the_default_view_and_included_when_requested(): void
    {
        Product::create(['sku' => 'ACTIVE1', 'name' => 'Active', 'category' => 'X', 'stock_unit' => 'unit', 'purchase_unit' => 'unit', 'selling_price' => '10', 'archived' => false]);
        Product::create(['sku' => 'ARCHIVED1', 'name' => 'Archived', 'category' => 'X', 'stock_unit' => 'unit', 'purchase_unit' => 'unit', 'selling_price' => '10', 'archived' => true]);

        $default = $this->actingAs($this->manager())->get('/catalog/products');
        $default->assertInertia(fn ($page) => $page->where('products.data', fn ($data) => collect($data)->pluck('sku')->all() === ['ACTIVE1']));

        $archivedView = $this->actingAs($this->manager())->get('/catalog/products?archived=1');
        $archivedView->assertInertia(fn ($page) => $page->where('products.data', fn ($data) => collect($data)->pluck('sku')->all() === ['ARCHIVED1']));
    }

    public function test_a_manager_can_archive_and_restore_a_product(): void
    {
        $product = Product::create(['sku' => 'ARCH', 'name' => 'X', 'category' => 'X', 'stock_unit' => 'unit', 'purchase_unit' => 'unit', 'selling_price' => '10']);
        $manager = $this->manager();

        $this->actingAs($manager)->put("/catalog/products/{$product->id}", [
            'name' => $product->name, 'category' => $product->category, 'selling_price' => '10',
            'tax_rate_percent' => '0', 'tax_inclusive' => true, 'reorder_point' => '0', 'archived' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($product->fresh()->archived);
    }

    public function test_pagination_limits_results_per_page(): void
    {
        Product::factory()->count(30)->create();

        $response = $this->actingAs($this->manager())->get('/catalog/products');
        $response->assertInertia(fn ($page) => $page->where('products.data', fn ($data) => count($data) === 25));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Reporting\ManagementReport;
use App\Support\Reporting\Reconciliation;
use App\Support\Reporting\ReportPeriod;
use App\Support\Reporting\ReportTable;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemoAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_demo_seeds_reconciles_and_every_business_page_and_report_loads(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->assertDatabaseCount('products', 150);
        $this->assertDatabaseCount('sales', 360);
        $this->assertDatabaseCount('customers', 40);
        $this->assertDatabaseCount('suppliers', 12);
        foreach (Reconciliation::check() as $name => $failures) {
            $this->assertSame(0, $failures, $name);
        }
        $this->seed(DemoDataSeeder::class);
        $this->assertDatabaseCount('sales', 360);
        $this->actingAs(User::where('username', 'admin')->firstOrFail());
        foreach (['/dashboard', '/pos', '/sales', '/returns', '/purchasing', '/purchasing/create', '/inventory', '/inventory/operations', '/expenses', '/customers', '/catalog/categories', '/catalog/products', '/catalog/suppliers', '/catalog/locations', '/admin/users', '/admin/audit'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (ReportTable::TYPES as $type) {
            $this->get('/reports?type='.$type)->assertOk();
            $export = $this->get('/reports/export?type='.$type)->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
            $this->assertNotEmpty($export->streamedContent());
        }
        $this->get('/sales/'.Sale::first()->id)->assertOk();
        $this->get('/purchasing/receipts/'.GoodsReceipt::first()->id)->assertOk();
        $this->get('/customers/'.Customer::first()->id)->assertOk();
        $this->get('/inventory/stock/'.Product::first()->id)->assertOk();
        $period = new ReportPeriod(now(config('erp.timezone'))->subDays(100)->format('Y-m-d'), now(config('erp.timezone'))->format('Y-m-d'));
        $summary = ManagementReport::summary($period);
        $expected = DB::selectOne('select (select coalesce(sum(subtotal),0) from sales) - (select coalesce(sum(total_amount-tax_amount),0) from sales_returns) as net');
        $this->assertSame((string) $expected->net, $summary['net_sales']);
        $this->assertSame(360, $summary['transactions']);
        $this->actingAs(User::where('username', 'cashier1')->firstOrFail());
        $this->get('/reports')->assertForbidden();
        $this->get('/expenses')->assertForbidden();
        $this->get('/inventory/stock/'.Product::first()->id)->assertInertia(fn (Assert $page) => $page->where('showCost', false)->missing('balances.0.average_cost'));
        $other = Sale::whereHas('cashier', fn ($q) => $q->where('username', 'cashier2'))->firstOrFail();
        $this->get('/sales/'.$other->id)->assertForbidden();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('summary', null));
    }
}

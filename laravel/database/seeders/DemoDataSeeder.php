<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\BusinessDate;
use App\Support\Decimal\Money;
use App\Support\Finance\ExpenseService;
use App\Support\Inventory\InventoryOperationService;
use App\Support\Purchasing\PurchaseOrderService;
use App\Support\Purchasing\PurchaseReturnService;
use App\Support\Purchasing\ReceivingService;
use App\Support\Purchasing\SupplierPaymentService;
use App\Support\Sales\CustomerPaymentService;
use App\Support\Sales\SaleService;
use App\Support\Sales\SalesReturnService;
use App\Support\Sales\ShiftService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo data is restricted to local/testing.');
        }
        if (DB::table('audit_log_entries')->where('action', 'DEMO_DATA_SEEDED')->exists()) {
            $this->command?->info('Demo data already exists; no duplicate transactions created.');

            return;
        }
        if (Product::exists() || DB::table('sales')->exists() || DB::table('goods_receipts')->exists()) {
            throw new \RuntimeException('Demo seeding requires an empty business database. Existing records were preserved.');
        }
        $originalNow = Carbon::getTestNow();
        $anchor = CarbonImmutable::parse(BusinessDate::today(), config('erp.timezone'))->startOfDay()->utc();
        try {
            DB::transaction(function () use ($anchor) {
                $this->call(RolesAndPermissionsSeeder::class);
                $users = [];
                foreach ([['admin', 'Ayesha Rahman', 'admin'], ['manager1', 'Farhan Ahmed', 'manager'], ['manager2', 'Nadia Islam', 'manager'], ['cashier1', 'Rafi Hasan', 'cashier'], ['cashier2', 'Mitu Akter', 'cashier'], ['cashier3', 'Samiul Karim', 'cashier'], ['inventory1', 'Imran Hossain', 'inventory'], ['accountant1', 'Tasnim Sultana', 'accountant']] as [$username,$name,$role]) {
                    $user = User::firstOrCreate(['username' => $username], ['name' => $name.' (demo)', 'email' => $username.'@example.test', 'password' => 'DemoOnly!2026', 'active' => true]);
                    $user->syncRoles([$role]);
                    $users[$username] = $user;
                }
                $manager = $users['manager1'];
                $floor = Location::create(['name' => 'Main store · sales floor', 'type' => 'sales_floor']);
                $stockroom = Location::create(['name' => 'Backroom warehouse', 'type' => 'stockroom']);
                $quarantine = Location::create(['name' => 'Quality hold', 'type' => 'quarantine']);
                $suppliers = [];
                foreach (['Meghna Grocery', 'Eastern Dairy', 'Green Harvest', 'Delta Beverage', 'Sunrise Bakery', 'City Homecare', 'Bay Frozen Foods', 'Riverbank Care', 'North Star Imports', 'Daily Essentials', 'Orchard Fruit', 'Bright Packaging'] as $i => $name) {
                    $suppliers[] = Supplier::create(['name' => $name.' Distribution (demo)', 'phone' => '01700'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT), 'address' => ($i + 12).' Demo Market Road, Dhaka']);
                }
                $customers = [];
                $first = ['Amina', 'Arif', 'Nusrat', 'Hasan', 'Shirin', 'Raihan', 'Faria', 'Sabbir'];
                $last = ['Rahman', 'Ahmed', 'Islam', 'Khan', 'Chowdhury'];
                for ($i = 0; $i < 40; $i++) {
                    $customers[] = Customer::create(['name' => $first[$i % 8].' '.$last[intdiv($i, 8)].' (demo)', 'phone' => '01800'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT), 'email' => 'shopper'.($i + 1).'@example.test', 'address' => ($i + 1).' Demo Housing Lane, Dhaka', 'credit_limit' => '25000', 'notes' => 'Synthetic demonstration customer.']);
                }
                $catalog = [
                    'Rice & grains' => ['Miniket rice', 'Nazirshail rice', 'Basmati rice', 'Brown rice', 'Flattened rice'],
                    'Pulses & flour' => ['Red lentils', 'Chickpeas', 'Mung dal', 'Whole wheat flour', 'Semolina'],
                    'Cooking essentials' => ['Soybean oil', 'Mustard oil', 'Sunflower oil', 'Ghee', 'White vinegar'],
                    'Spices' => ['Turmeric powder', 'Chilli powder', 'Cumin powder', 'Coriander powder', 'Mixed spice'],
                    'Dairy' => ['Fresh milk', 'Plain yogurt', 'Salted butter', 'Cheddar cheese', 'Milk powder'],
                    'Bakery' => ['Sandwich bread', 'Wholemeal bread', 'Butter buns', 'Plain cake', 'Toast biscuits'],
                    'Beverages' => ['Drinking water', 'Orange juice', 'Mango drink', 'Lemon soda', 'Coconut water'],
                    'Tea & coffee' => ['Black tea', 'Green tea', 'Instant coffee', 'Ground coffee', 'Milk tea mix'],
                    'Snacks' => ['Potato chips', 'Salted crackers', 'Peanut mix', 'Popcorn', 'Rice crackers'],
                    'Confectionery' => ['Milk chocolate', 'Dark chocolate', 'Fruit candy', 'Wafer rolls', 'Chocolate cookies'],
                    'Personal care' => ['Hand soap', 'Shampoo', 'Toothpaste', 'Body wash', 'Tissue box'],
                    'Household' => ['Laundry powder', 'Dishwashing liquid', 'Floor cleaner', 'Garbage bags', 'Kitchen towels'],
                    'Frozen foods' => ['Frozen peas', 'Chicken nuggets', 'Frozen paratha', 'Fish fingers', 'Mixed vegetables'],
                    'Produce' => ['Potatoes', 'Tomatoes', 'Onions', 'Carrots', 'Green chillies'],
                    'Fruit' => ['Bananas', 'Apples', 'Oranges', 'Papaya', 'Guava'],
                ];
                $brands = ['FreshMart Select', 'Green Valley', 'Daily Choice', 'Meadow', 'Sunrise', 'Pure Home', 'River Foods', 'Harvest'];
                $products = [];
                $receipts = [];
                $index = 0;
                Carbon::setTestNow($anchor->subDays(90)->addHours(3));
                foreach ($catalog as $category => $names) {
                    foreach ($names as $name) {
                        foreach (['Regular', 'Family pack'] as $size) {
                            $i = $index++;
                            $weighted = in_array($category, ['Produce', 'Fruit']);
                            $batch = in_array($category, ['Dairy', 'Bakery', 'Frozen foods']);
                            $cost = 25 + ($i * 23 % 160);
                            $price = $cost + 10 + ($i * 13 % 90);
                            $product = Product::factory()->create(['sku' => 'DEMO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'name' => $name.' · '.$size, 'category' => $category, 'brand' => $brands[$i % 8], 'stock_unit' => $weighted ? 'kg' : 'unit', 'purchase_unit' => 'carton', 'pack_conversion_factor' => '12', 'selling_price' => (string) $price, 'tax_rate_percent' => in_array($category, ['Produce', 'Fruit', 'Rice & grains']) ? '0' : '5', 'tax_inclusive' => true, 'is_weighted' => $weighted, 'is_batch_tracked' => $batch, 'reorder_point' => '12', 'default_supplier_id' => $suppliers[$i % 12]->id]);
                            $product->barcodes()->create(['code' => '290'.str_pad((string) ($i + 1), 10, '0', STR_PAD_LEFT)]);
                            $receipt = ReceivingService::post($manager, ['supplier_id' => $suppliers[$i % 12]->id, 'location_id' => $floor->id, 'supplier_reference' => 'DEMO-INV-'.($i + 1), 'lines' => [['product_id' => $product->id, 'quantity_received' => '100', 'unit_cost' => (string) $cost, 'batch_no' => $batch ? 'LOT-'.($i + 1).'-A' : null, 'expiry_date' => $batch ? $anchor->addDays($i % 4 === 0 ? 8 : 120)->format('Y-m-d') : null]]]);
                            SupplierPaymentService::post($manager, $receipt, ['amount' => (string) $receipt->total_amount->dividedBy($i % 3 === 0 ? 2 : 1), 'method' => 'bank', 'reference' => 'DEMO-BANK-'.($i + 1)]);
                            $products[] = $product;
                            $receipts[] = $receipt;
                        }
                    }
                }
                $categories = [];
                foreach (['Rent', 'Utilities', 'Staff meals', 'Transport', 'Cleaning', 'Repairs', 'Packaging', 'Internet'] as $name) {
                    $categories[] = ExpenseCategory::create(['name' => $name]);
                }
                $saleIndex = 0;
                for ($day = 89; $day >= 0; $day--) {
                    Carbon::setTestNow($anchor->subDays($day)->addMinutes($day === 0 ? 1 : 600));
                    $cashier = $users['cashier'.(($day % 3) + 1)];
                    $shift = ShiftService::open($cashier, ['location_id' => $floor->id, 'counter' => 'Till '.(($day % 3) + 1), 'opening_cash' => '2000']);
                    for ($j = 0; $j < 4; $j++) {
                        $i = $saleIndex++;
                        $lines = [];
                        $total = Money::zero();
                        for ($k = 0; $k < 3; $k++) {
                            $product = $products[($i * 7 + $k * 19) % 140];
                            $qty = $product->is_weighted ? ($k % 2 === 0 ? '0.750' : '1.250') : (string) (1 + (($i + $k) % 3));
                            $lines[] = ['product_id' => $product->id, 'quantity' => $qty];
                            $total = $total->plus($product->selling_price->multipliedBy($qty));
                        }
                        $credit = $i % 11 === 0;
                        $method = ['cash', 'cash', 'card', 'mobile'][$i % 4];
                        $amount = $credit ? $total->dividedBy(2) : $total;
                        $sale = SaleService::post($cashier, ['shift_id' => $shift->id, 'customer_id' => $customers[$i % 40]->id, 'lines' => $lines, 'payments' => [['method' => $method, 'amount' => (string) $amount, 'reference' => $method === 'cash' ? null : 'DEMO-TXN-'.($i + 1)]]])->refresh()->load('lines');
                        if ($credit && $i % 2 === 0) {
                            CustomerPaymentService::post($manager, $sale, ['amount' => (string) $total->minus($amount), 'method' => 'bank', 'reference' => 'DEMO-COLLECT-'.($i + 1)]);
                        }
                        if ($i % 29 === 0) {
                            $line = $sale->lines->first();
                            SalesReturnService::post($manager, $sale, ['location_id' => $i % 58 === 0 ? $quarantine->id : $floor->id, 'disposition' => $i % 58 === 0 ? 'quarantine' : 'restock', 'refund_method' => 'bank', 'reference' => 'DEMO-REFUND-'.($i + 1), 'reason' => $i % 58 === 0 ? 'Damaged packaging reported by customer' : 'Customer selected the wrong product', 'lines' => [['sale_line_id' => $line->id, 'quantity' => (string) $line->quantity]]]);
                        }
                    }
                    if ($day % 3 === 0) {
                        ExpenseService::post($manager, ['expense_category_id' => $categories[$day % 8]->id, 'amount' => (string) (250 + ($day * 91 % 3000)), 'method' => $day % 2 === 0 ? 'cash' : 'bank', 'reference' => $day % 2 === 0 ? null : 'DEMO-EXP-'.$day, 'paid_from' => $day % 2 === 0 ? 'Office petty cash' : 'Operating bank account', 'expense_date' => BusinessDate::today(), 'description' => 'Synthetic operating expense: '.$categories[$day % 8]->name]);
                    }
                    ShiftService::close($cashier, $shift, ['counted_cash' => (string) ShiftService::expectedCash($shift)]);
                }
                Carbon::setTestNow($anchor->addMinutes(2));
                for ($i = 0; $i < 5; $i++) {
                    InventoryOperationService::post($manager, ['type' => 'transfer', 'product_id' => $products[$i]->id, 'location_id' => $floor->id, 'destination_id' => $stockroom->id, 'quantity' => '10', 'reason' => 'Move reserve stock to backroom']);
                }
                for ($i = 130; $i < 140; $i++) {
                    $balance = $products[$i]->stockBalances()->where('location_id', $floor->id)->first();
                    $delta = $balance->quantity_on_hand->minus($i % 2 === 0 ? '0' : '4');
                    if ($delta->isPositive()) {
                        InventoryOperationService::post($manager, ['type' => 'adjustment', 'product_id' => $products[$i]->id, 'location_id' => $floor->id, 'batch_id' => $balance->batch_id, 'quantity' => (string) $delta->negated(), 'reason' => 'Synthetic count correction illustrating reorder alerts']);
                    }
                }
                InventoryOperationService::post($manager, ['type' => 'damage', 'product_id' => $products[15]->id, 'location_id' => $floor->id, 'quantity' => '2', 'reason' => 'Damaged cartons during shelf handling']);
                PurchaseReturnService::post($manager, $receipts[20], ['reason' => 'Supplier accepted damaged packaging', 'lines' => [['goods_receipt_line_id' => $receipts[20]->lines->first()->id, 'quantity' => '2']]]);
                for ($i = 0; $i < 6; $i++) {
                    PurchaseOrderService::create($manager, ['supplier_id' => $suppliers[$i]->id, 'location_id' => $stockroom->id, 'expected_on' => $anchor->addDays(3 + $i)->format('Y-m-d'), 'notes' => 'Synthetic replenishment order', 'lines' => [['product_id' => $products[$i]->id, 'quantity' => '24', 'unit_cost' => '45']]]);
                }
                AuditLog::record($manager, 'DEMO_DATA_SEEDED', null, ['version' => 1, 'products' => 150, 'sales' => 360, 'synthetic' => true]);
            });
        } finally {
            Carbon::setTestNow($originalNow);
        }
        $this->command?->info('Demo created: 150 products, 40 customers, 12 suppliers, 360 sales, 90 shifts, payments, returns, expenses, stock operations. New demo accounts use DemoOnly!2026; existing passwords are preserved.');
    }
}

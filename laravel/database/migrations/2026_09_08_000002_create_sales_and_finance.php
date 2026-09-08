<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('phone')->nullable()->index();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->text('notes')->nullable();
            $t->decimal('credit_limit', 14, 2)->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index(['active', 'name']);
        });
        Schema::create('shifts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('cashier_id')->constrained('users');
            $t->foreignUuid('location_id')->constrained();
            $t->string('counter');
            $t->string('status')->default('open');
            $t->decimal('opening_cash', 14, 2);
            $t->decimal('counted_cash', 14, 2)->nullable();
            $t->decimal('expected_cash', 14, 2)->nullable();
            $t->decimal('variance', 14, 2)->nullable();
            $t->text('closing_note')->nullable();
            $t->timestamp('opened_at');
            $t->timestamp('closed_at')->nullable();
            $t->index(['cashier_id', 'opened_at']);
        });
        DB::statement("CREATE UNIQUE INDEX shifts_open_cashier_unique ON shifts (cashier_id) WHERE status = 'open'");
        DB::statement("CREATE UNIQUE INDEX shifts_open_counter_unique ON shifts (location_id, counter) WHERE status = 'open'");
        Schema::create('sales', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('number')->unique();
            $t->foreignUuid('shift_id')->constrained();
            $t->foreignUuid('location_id')->constrained();
            $t->foreignUuid('cashier_id')->constrained('users');
            $t->foreignUuid('customer_id')->nullable()->constrained();
            foreach (['subtotal', 'tax_total', 'discount_total', 'grand_total', 'cost_total', 'paid_amount', 'returned_amount', 'refunded_amount', 'tendered_amount', 'change_amount'] as $field) {
                $t->decimal($field, 14, 2)->default(0);
            }
            $t->text('notes')->nullable();
            $t->timestamp('posted_at');
            $t->index(['cashier_id', 'posted_at']);
            $t->index(['customer_id', 'posted_at']);
            $t->index('posted_at');
            $t->index('shift_id');
        });
        Schema::create('sale_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('sale_id')->constrained();
            $t->foreignUuid('product_id')->constrained();
            $t->string('product_name');
            $t->string('sku');
            $t->string('stock_unit');
            $t->decimal('quantity', 14, 3);
            $t->decimal('returned_quantity', 14, 3)->default(0);
            $t->decimal('unit_price', 14, 2);
            $t->decimal('tax_rate_percent', 5, 2);
            $t->boolean('tax_inclusive');
            foreach (['discount_amount', 'subtotal', 'tax_amount', 'line_total', 'cost_total'] as $field) {
                $t->decimal($field, 14, 2)->default(0);
            }
            $t->unique(['sale_id', 'product_id']);
            $t->index('product_id');
        });
        Schema::create('sale_allocations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('sale_line_id')->constrained();
            $t->foreignUuid('batch_id')->nullable()->constrained();
            $t->decimal('quantity', 14, 3);
            $t->decimal('returned_quantity', 14, 3)->default(0);
            $t->decimal('unit_cost', 14, 6);
            $t->index('sale_line_id');
        });
        Schema::create('sales_returns', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('number')->unique();
            $t->foreignUuid('sale_id')->constrained();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->foreignUuid('location_id')->constrained();
            foreach (['total_amount', 'tax_amount', 'cost_amount', 'refund_amount'] as $field) {
                $t->decimal($field, 14, 2)->default(0);
            }
            $t->text('reason');
            $t->string('disposition');
            $t->timestamp('posted_at');
            $t->index(['sale_id', 'posted_at']);
            $t->index('posted_at');
        });
        Schema::create('sales_return_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('sales_return_id')->constrained();
            $t->foreignUuid('sale_line_id')->constrained();
            $t->decimal('quantity', 14, 3);
            foreach (['total_amount', 'tax_amount', 'cost_amount'] as $field) {
                $t->decimal($field, 14, 2);
            }
            $t->unique(['sales_return_id', 'sale_line_id']);
        });
        Schema::create('sales_payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('sale_id')->constrained();
            $t->foreignUuid('sales_return_id')->nullable()->constrained();
            $t->foreignUuid('shift_id')->nullable()->constrained();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->string('kind')->default('payment');
            $t->string('method');
            $t->decimal('amount', 14, 2);
            $t->string('reference')->nullable();
            $t->timestamp('paid_at');
            $t->index(['sale_id', 'paid_at']);
            $t->index(['shift_id', 'method']);
            $t->index('paid_at');
        });
        Schema::create('cash_movements', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('shift_id')->constrained();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->string('type');
            $t->decimal('amount', 14, 2);
            $t->text('reason');
            $t->timestamp('posted_at');
            $t->index('shift_id');
        });
        Schema::create('expense_categories', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('expenses', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('number')->unique();
            $t->foreignUuid('expense_category_id')->constrained();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->foreignUuid('location_id')->nullable()->constrained();
            $t->decimal('amount', 14, 2);
            $t->string('method');
            $t->string('reference')->nullable();
            $t->string('paid_from');
            $t->date('expense_date');
            $t->text('description');
            $t->timestamps();
            $t->index(['expense_category_id', 'expense_date']);
            $t->index('expense_date');
        });
        $checks = [
            'customers' => 'credit_limit >= 0',
            'shifts' => "opening_cash >= 0 AND (counted_cash IS NULL OR counted_cash >= 0) AND status IN ('open','closed') AND ((status = 'open' AND closed_at IS NULL) OR (status = 'closed' AND closed_at IS NOT NULL AND counted_cash IS NOT NULL AND expected_cash IS NOT NULL AND variance IS NOT NULL))",
            'sales' => 'subtotal >= 0 AND tax_total >= 0 AND discount_total >= 0 AND grand_total = subtotal + tax_total AND paid_amount >= 0 AND returned_amount BETWEEN 0 AND grand_total AND refunded_amount BETWEEN 0 AND paid_amount AND grand_total - returned_amount - paid_amount + refunded_amount >= 0 AND cost_total >= 0 AND change_amount >= 0',
            'sale_lines' => 'quantity > 0 AND returned_quantity BETWEEN 0 AND quantity AND unit_price >= 0 AND discount_amount >= 0 AND subtotal >= 0 AND tax_amount >= 0 AND line_total = subtotal + tax_amount AND cost_total >= 0',
            'sale_allocations' => 'quantity > 0 AND returned_quantity BETWEEN 0 AND quantity AND unit_cost >= 0',
            'sales_returns' => "total_amount >= 0 AND tax_amount >= 0 AND cost_amount >= 0 AND refund_amount BETWEEN 0 AND total_amount AND disposition IN ('restock','quarantine')",
            'sales_return_lines' => 'quantity > 0 AND total_amount >= 0 AND tax_amount >= 0 AND cost_amount >= 0',
            'sales_payments' => "amount > 0 AND kind IN ('payment','refund') AND method IN ('cash','card','mobile','bank') AND (method <> 'cash' OR shift_id IS NOT NULL) AND ((kind = 'payment' AND sales_return_id IS NULL) OR (kind = 'refund' AND sales_return_id IS NOT NULL))",
            'cash_movements' => "amount > 0 AND type IN ('in','out')",
            'expenses' => "amount > 0 AND method IN ('cash','card','mobile','bank')",
        ];
        foreach ($checks as $table => $check) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_domain_check CHECK ({$check})");
        }
    }

    public function down(): void
    {
        foreach (['expenses', 'expense_categories', 'cash_movements', 'sales_payments', 'sales_return_lines', 'sales_returns', 'sale_allocations', 'sale_lines', 'sales', 'shifts', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

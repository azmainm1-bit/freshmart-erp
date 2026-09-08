<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $t) {
            $t->string('prefix')->primary();
            $t->bigInteger('value')->default(0);
        });
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('number')->unique();
            $t->foreignUuid('supplier_id')->constrained();
            $t->foreignUuid('location_id')->constrained();
            $t->foreignUuid('created_by_id')->constrained('users');
            $t->string('status')->default('ordered');
            $t->date('expected_on')->nullable();
            $t->text('notes')->nullable();
            $t->decimal('total_amount', 14, 2);
            $t->timestamps();
            $t->index(['supplier_id', 'created_at']);
            $t->index(['status', 'created_at']);
        });
        Schema::create('purchase_order_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('purchase_order_id')->constrained();
            $t->foreignUuid('product_id')->constrained();
            $t->decimal('quantity', 14, 3);
            $t->decimal('received_quantity', 14, 3)->default(0);
            $t->decimal('unit_cost', 14, 6);
            $t->decimal('line_total', 14, 2);
            $t->unique(['purchase_order_id', 'product_id']);
        });
        Schema::table('goods_receipts', function (Blueprint $t) {
            $t->foreign('purchase_order_id')->references('id')->on('purchase_orders');
            $t->string('number')->nullable()->unique();
            $t->string('supplier_reference')->nullable();
            $t->decimal('total_amount', 14, 2)->default(0);
            $t->decimal('paid_amount', 14, 2)->default(0);
            $t->decimal('returned_amount', 14, 2)->default(0);
            $t->text('notes')->nullable();
            $t->unique(['supplier_id', 'supplier_reference']);
            $t->index(['supplier_id', 'posted_at']);
            $t->index('posted_at');
        });
        DB::statement('update goods_receipts set total_amount = coalesce((select sum(line_total) from goods_receipt_lines where goods_receipt_id = goods_receipts.id), 0)');
        Schema::table('goods_receipt_lines', function (Blueprint $t) {
            $t->decimal('returned_quantity', 14, 3)->default(0);
            $t->index('goods_receipt_id');
        });
        Schema::create('supplier_payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('goods_receipt_id')->constrained();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->decimal('amount', 14, 2);
            $t->string('method');
            $t->string('reference')->nullable();
            $t->timestamp('paid_at');
            $t->index(['goods_receipt_id', 'paid_at']);
            $t->index('paid_at');
        });
        Schema::create('purchase_returns', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('number')->unique();
            $t->foreignUuid('goods_receipt_id')->constrained();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->decimal('total_amount', 14, 2);
            $t->text('reason');
            $t->timestamp('posted_at');
            $t->index(['goods_receipt_id', 'posted_at']);
        });
        Schema::create('purchase_return_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('purchase_return_id')->constrained();
            $t->foreignUuid('goods_receipt_line_id')->constrained();
            $t->decimal('quantity', 14, 3);
            $t->decimal('amount', 14, 2);
            $t->unique(['purchase_return_id', 'goods_receipt_line_id']);
        });
        Schema::create('stock_operations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('number')->unique();
            $t->string('type');
            $t->foreignUuid('product_id')->constrained();
            $t->foreignUuid('location_id')->constrained();
            $t->foreignUuid('destination_id')->nullable()->constrained('locations');
            $t->foreignUuid('batch_id')->nullable()->constrained('batches');
            $t->foreignUuid('actor_id')->constrained('users');
            $t->decimal('quantity', 14, 3);
            $t->decimal('unit_cost', 14, 6);
            $t->text('reason');
            $t->timestamp('posted_at');
            $t->index(['type', 'posted_at']);
        });
        Schema::table('batches', function (Blueprint $t) {
            $t->unique(['id', 'product_id']);
            $t->index('expiry_date');
        });
        foreach (['stock_balances', 'stock_movements', 'goods_receipt_lines'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreign(['batch_id', 'product_id'])->references(['id', 'product_id'])->on('batches');
            });
        }
        Schema::table('stock_movements', function (Blueprint $t) {
            $t->index(['product_id', 'created_at']);
            $t->index('created_at');
        });
        $checks = [
            'products' => 'selling_price >= 0 AND tax_rate_percent BETWEEN 0 AND 100 AND pack_conversion_factor > 0 AND reorder_point >= 0',
            'stock_balances' => 'quantity_on_hand >= 0 AND average_cost >= 0',
            'stock_movements' => 'quantity_delta <> 0 AND unit_cost >= 0',
            'goods_receipt_lines' => 'quantity_received > 0 AND unit_cost >= 0 AND line_total >= 0 AND returned_quantity BETWEEN 0 AND quantity_received',
            'goods_receipts' => 'total_amount >= 0 AND paid_amount >= 0 AND returned_amount BETWEEN 0 AND total_amount',
            'purchase_orders' => "total_amount >= 0 AND status IN ('ordered','partial','received','cancelled')",
            'purchase_order_lines' => 'quantity > 0 AND received_quantity BETWEEN 0 AND quantity AND unit_cost >= 0 AND line_total >= 0',
            'supplier_payments' => "amount > 0 AND method IN ('cash','bank','card','mobile')",
            'purchase_returns' => 'total_amount >= 0',
            'purchase_return_lines' => 'quantity > 0 AND amount >= 0',
            'stock_operations' => "quantity <> 0 AND unit_cost >= 0 AND type IN ('adjustment','damage','expired','transfer') AND (type <> 'transfer' OR (destination_id IS NOT NULL AND destination_id <> location_id AND quantity > 0))",
        ];
        foreach ($checks as $table => $check) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_domain_check CHECK ({$check})");
        }
    }

    public function down(): void
    {
        foreach (['products', 'stock_balances', 'stock_movements', 'goods_receipt_lines', 'goods_receipts'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_domain_check");
        }
        foreach (['stock_balances', 'stock_movements', 'goods_receipt_lines'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign(['batch_id', 'product_id']));
        }
        Schema::table('batches', function (Blueprint $t) {
            $t->dropUnique(['id', 'product_id']);
            $t->dropIndex(['expiry_date']);
        });
        Schema::table('stock_movements', function (Blueprint $t) {
            $t->dropIndex(['product_id', 'created_at']);
            $t->dropIndex(['created_at']);
        });
        foreach (['stock_operations', 'purchase_return_lines', 'purchase_returns', 'supplier_payments'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('goods_receipts', function (Blueprint $t) {
            $t->dropForeign(['purchase_order_id']);
            $t->dropUnique(['supplier_id', 'supplier_reference']);
            $t->dropIndex(['supplier_id', 'posted_at']);
            $t->dropIndex(['posted_at']);
            $t->dropColumn(['number', 'supplier_reference', 'total_amount', 'paid_amount', 'returned_amount', 'notes']);
        });
        Schema::table('goods_receipt_lines', function (Blueprint $t) {
            $t->dropColumn('returned_quantity');
            $t->dropIndex(['goods_receipt_id']);
        });
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('document_sequences');
    }
};

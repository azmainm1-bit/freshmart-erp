<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('goods_receipt_id')->constrained('goods_receipts');
            $table->foreignUuid('product_id')->constrained('products');
            $table->foreignUuid('batch_id')->nullable()->constrained('batches');
            $table->decimal('quantity_received', 14, 3);
            $table->decimal('unit_cost', 14, 6);
            $table->decimal('line_total', 14, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};

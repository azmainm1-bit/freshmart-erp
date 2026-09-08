<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Append-only. Every stock quantity change of any kind is one row here. */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products');
            $table->foreignUuid('location_id')->constrained('locations');
            $table->foreignUuid('batch_id')->nullable()->constrained('batches');
            $table->decimal('quantity_delta', 14, 3); // signed
            $table->decimal('unit_cost', 14, 6);
            // opening_balance | goods_receipt | sale | return | adjustment
            // | write_off | purchase_return | transfer_out | transfer_in
            $table->string('movement_type');
            $table->string('source_document_type');
            $table->uuid('source_document_id');
            $table->foreignUuid('actor_id')->constrained('users');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'location_id']);
            $table->index(['source_document_type', 'source_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

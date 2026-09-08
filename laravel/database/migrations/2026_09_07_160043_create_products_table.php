<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku')->unique();
            $table->string('name');
            // Bangla name — docs/ARCHITECTURE.md catalog requirement. Nullable:
            // not every product needs one on day one.
            $table->string('name_bn')->nullable();
            $table->string('category');
            $table->string('brand')->nullable();
            $table->string('stock_unit'); // unit sold/stocked in, e.g. "bottle", "kg"
            $table->string('purchase_unit'); // unit bought in, e.g. "carton"
            $table->decimal('pack_conversion_factor', 14, 6)->default(1); // 1 purchase_unit = N stock_unit
            $table->decimal('selling_price', 14, 2);
            $table->decimal('tax_rate_percent', 5, 2)->default(0);
            $table->boolean('tax_inclusive')->default(true);
            $table->boolean('is_weighted')->default(false); // fractional stock_unit quantities allowed
            $table->boolean('is_batch_tracked')->default(false); // perishables: track batch/expiry
            $table->decimal('reorder_point', 14, 3)->default(0);
            $table->foreignUuid('default_supplier_id')->nullable()->constrained('suppliers');
            $table->boolean('archived')->default(false);
            $table->timestamps();

            $table->index('category');
            $table->index('archived');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

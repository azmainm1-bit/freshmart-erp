<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers');
            $table->foreignUuid('location_id')->constrained('locations');
            // Nullable: direct receipt without a PO is required functionality
            // (docs/ARCHITECTURE.md) — purchase_orders table doesn't exist yet
            // (Migration-Phase 4), so no FK constraint until then.
            $table->uuid('purchase_order_id')->nullable();
            $table->foreignUuid('received_by_id')->constrained('users');
            $table->timestamp('posted_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The single ledger of truth for on-hand quantity + moving weighted-
     * average cost per (product, location, batch). Every write happens
     * inside App\Support\Inventory\StockLedger::postMovement() under a row
     * lock — docs/ARCHITECTURE.md
     *
     * Uniqueness is enforced by two PARTIAL indexes, not a plain
     * composite unique constraint: Postgres treats NULL as distinct from
     * NULL in an ordinary unique index, which would silently allow
     * duplicate balance rows for every non-batch-tracked product
     * (batch_id IS NULL is the common case). This is a real bug the
     * legacy Node/Prisma implementation found and fixed
     * (server/prisma/migrations/20260907135108_stock_balance_index_fix) —
     * ported here directly rather than reintroduced. See
     * docs/ARCHITECTURE.md.
     */
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products');
            $table->foreignUuid('location_id')->constrained('locations');
            $table->foreignUuid('batch_id')->nullable()->constrained('batches');
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->decimal('average_cost', 14, 6)->default(0);
            $table->timestamp('updated_at')->useCurrent();

            $table->index(['product_id', 'location_id']);
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX stock_balances_no_batch_uidx
              ON stock_balances (product_id, location_id)
              WHERE batch_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX stock_balances_with_batch_uidx
              ON stock_balances (product_id, location_id, batch_id)
              WHERE batch_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * See docs/ARCHITECTURE.md for the full design rationale. Uniqueness is
     * scoped to (user_id, endpoint, key) — not global — so two different
     * staff members can't collide on a client-generated key. There is no
     * "pending" status column: a row only ever becomes durably visible to
     * another transaction once it has been committed with its response
     * already attached (the insert and the business write it guards
     * happen in the same outer transaction), so any row a query can see is
     * complete by construction.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('endpoint');
            $table->string('key');
            $table->string('fingerprint');
            $table->unsignedSmallInteger('response_status');
            $table->jsonb('response_body');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'endpoint', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};

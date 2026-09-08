<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->constrained('users');
            // e.g. "SALE_POSTED", "RETURN_APPROVED", "USER_DEACTIVATED"
            $table->string('action');
            // Polymorphic reference to the affected record, e.g. Sale/Return/User.
            $table->string('auditable_type')->nullable();
            $table->uuid('auditable_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_entries');
    }
};

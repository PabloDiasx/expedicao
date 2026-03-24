<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nomus_sales_sync_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('sync_key', 40);
            $table->timestamp('last_synced_modified_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->unsignedInteger('last_items_count')->default(0);
            $table->unsignedInteger('last_created_count')->default(0);
            $table->unsignedInteger('last_updated_count')->default(0);
            $table->unsignedInteger('last_pages_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'sync_key'], 'nsss_tenant_sync_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomus_sales_sync_states');
    }
};

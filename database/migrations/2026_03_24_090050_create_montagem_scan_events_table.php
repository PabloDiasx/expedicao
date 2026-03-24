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
        Schema::create('montagem_scan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->foreignId('equipment_model_id')->constrained('equipment_models')->restrictOnDelete();
            $table->foreignId('sales_order_id')->constrained('nomus_sales_orders')->restrictOnDelete();
            $table->foreignId('sales_order_item_id')->constrained('nomus_sales_order_items')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('barcode_input', 120);
            $table->string('barcode_normalized', 120);
            $table->string('device_identifier', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scanned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'equipment_id'], 'mse_tenant_equipment_unique');
            $table->index(['tenant_id', 'sales_order_item_id'], 'mse_tenant_item_idx');
            $table->index(['tenant_id', 'scanned_at'], 'mse_tenant_scanned_idx');
            $table->index(['tenant_id', 'equipment_model_id', 'scanned_at'], 'mse_tenant_model_scanned_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('montagem_scan_events');
    }
};

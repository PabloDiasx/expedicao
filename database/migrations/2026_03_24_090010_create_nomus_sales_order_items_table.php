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
        Schema::create('nomus_sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained('nomus_sales_orders')->cascadeOnDelete();
            $table->string('item_code', 20);
            $table->unsignedBigInteger('product_external_id')->nullable();
            $table->decimal('quantity', 14, 4)->default(0);
            $table->decimal('allocated_quantity', 14, 4)->default(0);
            $table->unsignedSmallInteger('item_status')->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('product_code', 80)->nullable();
            $table->string('product_name', 180)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['sales_order_id', 'item_code'], 'nsoi_order_item_unique');
            $table->index(['tenant_id', 'item_status', 'delivery_date'], 'nsoi_tenant_status_delivery_idx');
            $table->index(['tenant_id', 'product_external_id'], 'nsoi_tenant_product_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomus_sales_order_items');
    }
};

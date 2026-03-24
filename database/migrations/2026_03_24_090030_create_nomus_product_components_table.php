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
        Schema::create('nomus_product_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id');
            $table->unsignedBigInteger('parent_product_external_id');
            $table->unsignedBigInteger('component_product_external_id')->nullable();
            $table->string('component_codigo', 80)->nullable();
            $table->string('component_nome', 180)->nullable();
            $table->decimal('quantity_required', 14, 4)->default(0);
            $table->boolean('optional')->default(false);
            $table->boolean('item_de_embarque')->default(false);
            $table->unsignedSmallInteger('natureza_consumo')->nullable();
            $table->timestamp('nomus_updated_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'external_id'], 'npc_tenant_external_unique');
            $table->index(['tenant_id', 'parent_product_external_id'], 'npc_tenant_parent_idx');
            $table->index(['tenant_id', 'component_product_external_id'], 'npc_tenant_component_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomus_product_components');
    }
};

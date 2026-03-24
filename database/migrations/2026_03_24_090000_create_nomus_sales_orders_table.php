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
        Schema::create('nomus_sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id');
            $table->string('codigo_pedido', 40);
            $table->unsignedBigInteger('empresa_external_id')->nullable();
            $table->unsignedBigInteger('cliente_external_id')->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('data_entrega_padrao')->nullable();
            $table->timestamp('nomus_created_at')->nullable();
            $table->timestamp('nomus_updated_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'external_id'], 'nso_tenant_external_unique');
            $table->index(['tenant_id', 'codigo_pedido'], 'nso_tenant_codigo_idx');
            $table->index(['tenant_id', 'data_entrega_padrao'], 'nso_tenant_entrega_idx');
            $table->index(['tenant_id', 'nomus_updated_at'], 'nso_tenant_updated_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomus_sales_orders');
    }
};

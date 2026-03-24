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
        Schema::create('nomus_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id');
            $table->string('codigo', 80)->nullable();
            $table->string('nome', 180)->nullable();
            $table->string('descricao', 255)->nullable();
            $table->string('nome_tipo_produto', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('nomus_updated_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'external_id'], 'nprod_tenant_external_unique');
            $table->index(['tenant_id', 'codigo'], 'nprod_tenant_codigo_idx');
            $table->index(['tenant_id', 'nome'], 'nprod_tenant_nome_idx');
            $table->index(['tenant_id', 'nomus_updated_at'], 'nprod_tenant_updated_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomus_products');
    }
};

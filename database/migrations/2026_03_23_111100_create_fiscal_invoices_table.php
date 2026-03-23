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
        Schema::create('fiscal_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id');
            $table->string('numero', 50)->nullable();
            $table->string('serie', 20)->nullable();
            $table->string('chave', 64)->nullable();
            $table->string('cnpj_emitente', 18)->nullable();
            $table->string('protocolo', 50)->nullable();
            $table->string('recibo', 50)->nullable();
            $table->unsignedTinyInteger('ambiente')->nullable();
            $table->unsignedTinyInteger('finalidade')->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->unsignedTinyInteger('tipo_emissao')->nullable();
            $table->unsignedTinyInteger('tipo_operacao')->nullable();
            $table->boolean('is_fornecedor')->default(false);
            $table->string('usuario', 150)->nullable();
            $table->date('data_processamento')->nullable();
            $table->string('hora_processamento', 12)->nullable();
            $table->timestamp('nomus_created_at')->nullable();
            $table->timestamp('nomus_updated_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'external_id']);
            $table->index(['tenant_id', 'nomus_updated_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'numero', 'serie']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_invoices');
    }
};

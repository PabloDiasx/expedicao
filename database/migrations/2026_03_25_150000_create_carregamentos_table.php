<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carregamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('fiscal_invoice_id')->constrained('fiscal_invoices')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motorista_nome', 150);
            $table->string('motorista_documento', 30);
            $table->string('motorista_empresa', 150)->nullable();
            $table->string('placa_veiculo', 15);
            $table->string('status', 20)->default('aberto'); // aberto, concluido, cancelado
            $table->timestamps();

            $table->index(['tenant_id', 'fiscal_invoice_id']);
        });

        Schema::create('carregamento_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carregamento_id')->constrained('carregamentos')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->restrictOnDelete();
            $table->string('serial_number', 80);
            $table->string('barcode_scanned', 120)->nullable();
            $table->boolean('conferido')->default(false);
            $table->timestamp('conferido_at')->nullable();
            $table->timestamps();

            $table->unique(['carregamento_id', 'equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carregamento_items');
        Schema::dropIfExists('carregamentos');
    }
};

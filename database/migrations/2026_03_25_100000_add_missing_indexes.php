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
        Schema::table('barcode_reads', function (Blueprint $table) {
            $table->index('equipment_id', 'br_equipment_idx');
            $table->index('read_result', 'br_read_result_idx');
        });

        Schema::table('fiscal_invoices', function (Blueprint $table) {
            $table->index(['tenant_id', 'chave'], 'fi_tenant_chave_idx');
            $table->index(['tenant_id', 'cnpj_emitente'], 'fi_tenant_cnpj_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barcode_reads', function (Blueprint $table) {
            $table->dropIndex('br_equipment_idx');
            $table->dropIndex('br_read_result_idx');
        });

        Schema::table('fiscal_invoices', function (Blueprint $table) {
            $table->dropIndex('fi_tenant_chave_idx');
            $table->dropIndex('fi_tenant_cnpj_idx');
        });
    }
};

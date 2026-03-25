<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->string('vendedor', 150)->nullable()->after('notes');
            $table->string('cliente_venda', 150)->nullable()->after('vendedor');
            $table->string('destino_venda', 200)->nullable()->after('cliente_venda');
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropColumn(['vendedor', 'cliente_venda', 'destino_venda']);
        });
    }
};

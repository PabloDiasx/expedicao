<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carregamento_items', function (Blueprint $table) {
            $table->dropForeign(['equipment_id']);
            $table->dropUnique(['carregamento_id', 'equipment_id']);
        });

        Schema::table('carregamento_items', function (Blueprint $table) {
            $table->unsignedBigInteger('equipment_id')->nullable()->change();
            $table->foreign('equipment_id')->references('id')->on('equipments')->restrictOnDelete();
            $table->unique(['carregamento_id', 'serial_number']);
        });
    }

    public function down(): void
    {
        Schema::table('carregamento_items', function (Blueprint $table) {
            $table->dropForeign(['equipment_id']);
            $table->dropUnique(['carregamento_id', 'serial_number']);
        });

        Schema::table('carregamento_items', function (Blueprint $table) {
            $table->unsignedBigInteger('equipment_id')->nullable(false)->change();
            $table->foreign('equipment_id')->references('id')->on('equipments')->restrictOnDelete();
            $table->unique(['carregamento_id', 'equipment_id']);
        });
    }
};

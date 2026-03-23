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
        Schema::create('equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('equipment_model_id')->constrained('equipment_models')->restrictOnDelete();
            $table->string('serial_number', 80);
            $table->string('barcode', 120);
            $table->foreignId('current_status_id')->constrained('statuses')->restrictOnDelete();
            $table->foreignId('current_sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->date('manufactured_at')->nullable();
            $table->date('assembled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['current_status_id', 'current_sector_id']);
            $table->unique(['tenant_id', 'serial_number']);
            $table->unique(['tenant_id', 'barcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipments');
    }
};

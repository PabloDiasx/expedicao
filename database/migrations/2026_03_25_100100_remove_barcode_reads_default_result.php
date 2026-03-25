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
            $table->string('read_result', 30)->default(null)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barcode_reads', function (Blueprint $table) {
            $table->string('read_result', 30)->default('matched')->nullable(false)->change();
        });
    }
};

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
        Schema::table('nomus_sales_order_items', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('deleted_reason', 50)->nullable()->after('deleted_at');
        });

        Schema::table('nomus_product_components', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('deleted_reason', 50)->nullable()->after('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nomus_sales_order_items', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'deleted_reason']);
        });

        Schema::table('nomus_product_components', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'deleted_reason']);
        });
    }
};

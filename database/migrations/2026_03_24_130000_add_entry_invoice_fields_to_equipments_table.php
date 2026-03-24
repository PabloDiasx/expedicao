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
        Schema::table('equipments', function (Blueprint $table) {
            $table->unsignedBigInteger('entry_invoice_id')->nullable()->after('notes');
            $table->unsignedBigInteger('entry_invoice_external_id')->nullable()->after('entry_invoice_id');
            $table->string('entry_invoice_number', 50)->nullable()->after('entry_invoice_external_id');
            $table->string('entry_customer_name', 180)->nullable()->after('entry_invoice_number');
            $table->string('entry_destination', 180)->nullable()->after('entry_customer_name');
            $table->timestamp('entry_invoice_linked_at')->nullable()->after('entry_destination');

            $table->index(['tenant_id', 'entry_customer_name']);
            $table->index(['tenant_id', 'entry_invoice_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'entry_customer_name']);
            $table->dropIndex(['tenant_id', 'entry_invoice_number']);

            $table->dropColumn([
                'entry_invoice_id',
                'entry_invoice_external_id',
                'entry_invoice_number',
                'entry_customer_name',
                'entry_destination',
                'entry_invoice_linked_at',
            ]);
        });
    }
};


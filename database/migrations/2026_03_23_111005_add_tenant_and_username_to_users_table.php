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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->nullOnDelete();

            $table->string('username', 80)->nullable()->after('name');
            $table->index(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'username']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_id_username_unique');
            $table->dropIndex('users_tenant_id_email_index');
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('username');
        });
    }
};


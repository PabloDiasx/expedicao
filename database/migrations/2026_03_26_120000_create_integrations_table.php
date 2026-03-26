<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('slug', 50);           // nomus, custom_api_1, etc
            $table->string('name', 150);           // Nome exibido
            $table->string('description', 500)->nullable();
            $table->string('type', 30);            // erp, api, webhook
            $table->string('base_url', 500)->nullable();
            $table->string('auth_type', 30)->nullable();  // basic, bearer, api_key, none
            $table->text('auth_value')->nullable();        // token/key (encrypted)
            $table->boolean('verify_ssl')->default(true);
            $table->integer('timeout_seconds')->default(30);
            $table->string('status', 20)->default('disconnected'); // connected, disconnected, error
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_result', 500)->nullable();
            $table->boolean('is_native')->default(false);  // true = came with the system
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        // Seed Nomus as native integration for existing tenants
        $tenants = DB::table('tenants')->pluck('id');
        $now = now();
        foreach ($tenants as $tenantId) {
            $nomusLocation = config('services.nomus.location', '');
            $nomusKey = config('services.nomus.integration_key', '');

            DB::table('integrations')->insert([
                'tenant_id' => $tenantId,
                'slug' => 'nomus',
                'name' => 'Nomus ERP',
                'description' => 'Sistema ERP para gestão de notas fiscais, pedidos de venda e produtos.',
                'type' => 'erp',
                'base_url' => $nomusLocation,
                'auth_type' => 'basic',
                'auth_value' => $nomusKey,
                'verify_ssl' => (bool) config('services.nomus.verify_ssl', true),
                'timeout_seconds' => (int) config('services.nomus.timeout_seconds', 30),
                'status' => $nomusLocation && $nomusKey ? 'connected' : 'disconnected',
                'is_native' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('cnpj', 20)->nullable()->after('domain');
            $table->string('razao_social', 200)->nullable()->after('cnpj');
            $table->string('telefone', 20)->nullable()->after('razao_social');
            $table->string('email', 191)->nullable()->after('telefone');
            $table->string('endereco', 300)->nullable()->after('email');
            $table->string('cidade', 100)->nullable()->after('endereco');
            $table->string('estado', 2)->nullable()->after('cidade');
            $table->string('cep', 10)->nullable()->after('estado');
            $table->string('logo_path', 300)->nullable()->after('cep');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('plan', 50);          // free, starter, business, enterprise
            $table->string('plan_label', 100);    // Nome exibido
            $table->decimal('price', 10, 2)->default(0);
            $table->string('billing_cycle', 20)->default('monthly'); // monthly, yearly
            $table->integer('max_users')->default(5);
            $table->integer('max_equipments')->default(100);
            $table->boolean('has_integrations')->default(false);
            $table->boolean('has_reports')->default(false);
            $table->date('started_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 20)->default('active'); // active, expired, cancelled
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'cnpj', 'razao_social', 'telefone', 'email',
                'endereco', 'cidade', 'estado', 'cep', 'logo_path',
            ]);
        });
    }
};

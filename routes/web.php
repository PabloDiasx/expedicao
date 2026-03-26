<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentModelController;
use App\Http\Controllers\ExpeditionController;
use App\Http\Controllers\CarregamentoController;
use App\Http\Controllers\ConfiguracoesController;
use App\Http\Controllers\HistoricoController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/esqueci-senha', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/esqueci-senha', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/redefinir-senha/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/redefinir-senha', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Operator+ — scan operations & read-only views
    Route::get('/producao', [ProductionController::class, 'index'])->name('production.index');
    Route::post('/producao/leitura', [ProductionController::class, 'store'])
        ->middleware('throttle:120,1')
        ->name('production.store');
    Route::get('/expedicao', [ExpeditionController::class, 'index'])->name('expedition.index');
    Route::get('/expedicao/consulta-nf', [ExpeditionController::class, 'lookupInvoice'])->name('expedition.lookup-invoice');
    Route::post('/expedicao/leitura', [ExpeditionController::class, 'store'])
        ->middleware('throttle:120,1')
        ->name('expedition.store');

    // Supervisor+ — full read access
    Route::middleware('role:supervisor')->group(function (): void {
        Route::get('/historicos', [HistoricoController::class, 'index'])->name('historicos.index');
        Route::get('/equipamentos', [EquipmentController::class, 'index'])->name('equipments.index');
        Route::get('/equipamentos/{equipment}', [EquipmentController::class, 'show'])
            ->whereNumber('equipment')
            ->name('equipments.show');
        Route::put('/equipamentos/{equipment}', [EquipmentController::class, 'update'])
            ->whereNumber('equipment')
            ->name('equipments.update');
        Route::patch('/equipamentos/{equipment}/status', [EquipmentController::class, 'updateStatus'])
            ->whereNumber('equipment')
            ->name('equipments.update-status');
        Route::delete('/equipamentos/{equipment}', [EquipmentController::class, 'destroy'])
            ->whereNumber('equipment')
            ->name('equipments.destroy');
        Route::get('/notas-fiscais', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/notas-fiscais/{invoice}', [InvoiceController::class, 'show'])
            ->whereNumber('invoice')
            ->name('invoices.show');
        Route::get('/notas-fiscais/{invoice}/danfe', [InvoiceController::class, 'danfe'])
            ->whereNumber('invoice')
            ->name('invoices.danfe');
        Route::post('/carregamentos', [CarregamentoController::class, 'store'])->name('carregamentos.store');
        Route::get('/carregamentos/{carregamento}', [CarregamentoController::class, 'show'])
            ->whereNumber('carregamento')
            ->name('carregamentos.show');
        Route::post('/carregamentos/{carregamento}/scan', [CarregamentoController::class, 'scan'])
            ->whereNumber('carregamento')
            ->middleware('throttle:120,1')
            ->name('carregamentos.scan');
        Route::put('/carregamentos/{carregamento}', [CarregamentoController::class, 'update'])
            ->whereNumber('carregamento')
            ->name('carregamentos.update');
        Route::delete('/carregamentos/{carregamento}', [CarregamentoController::class, 'destroy'])
            ->whereNumber('carregamento')
            ->name('carregamentos.destroy');
        Route::post('/carregamentos/{carregamento}/finalizar', [CarregamentoController::class, 'finalizar'])
            ->whereNumber('carregamento')
            ->name('carregamentos.finalizar');
    });

    // Admin+ — configuracoes
    Route::middleware('role:admin')->group(function (): void {
        Route::get('/configuracoes', [ConfiguracoesController::class, 'index'])->name('configuracoes.index');
        Route::post('/configuracoes/usuarios', [ConfiguracoesController::class, 'storeUser'])->name('configuracoes.users.store');
        Route::put('/configuracoes/usuarios/{user}', [ConfiguracoesController::class, 'updateUser'])
            ->whereNumber('user')
            ->name('configuracoes.users.update');
        Route::delete('/configuracoes/usuarios/{user}', [ConfiguracoesController::class, 'destroyUser'])
            ->whereNumber('user')
            ->name('configuracoes.users.destroy');
        Route::put('/configuracoes/usuarios/{user}/permissoes', [ConfiguracoesController::class, 'updatePermissions'])
            ->whereNumber('user')
            ->name('configuracoes.users.permissions');
        Route::put('/configuracoes/empresa', [ConfiguracoesController::class, 'updateCompany'])
            ->name('configuracoes.company.update');
        Route::post('/configuracoes/integracoes', [ConfiguracoesController::class, 'storeIntegration'])
            ->name('configuracoes.integrations.store');
        Route::put('/configuracoes/integracoes/{integration}', [ConfiguracoesController::class, 'updateIntegration'])
            ->whereNumber('integration')
            ->name('configuracoes.integrations.update');
        Route::delete('/configuracoes/integracoes/{integration}', [ConfiguracoesController::class, 'destroyIntegration'])
            ->whereNumber('integration')
            ->name('configuracoes.integrations.destroy');
        Route::put('/configuracoes/integracoes/{integration}/webhook-config', [ConfiguracoesController::class, 'updateWebhookConfig'])
            ->whereNumber('integration')
            ->name('configuracoes.integrations.webhook-config');
        Route::post('/configuracoes/integracoes/{integration}/testar', [ConfiguracoesController::class, 'testIntegration'])
            ->whereNumber('integration')
            ->name('configuracoes.integrations.test');
    });

    // Admin only — manage models
    Route::middleware('role:admin')->group(function (): void {
        Route::get('/modelos-equipamentos', [EquipmentModelController::class, 'index'])->name('equipment-models.index');
        Route::post('/modelos-equipamentos', [EquipmentModelController::class, 'store'])->name('equipment-models.store');
        Route::put('/modelos-equipamentos/{model}', [EquipmentModelController::class, 'update'])
            ->whereNumber('model')
            ->name('equipment-models.update');
        Route::delete('/modelos-equipamentos/{model}', [EquipmentModelController::class, 'destroy'])
            ->whereNumber('model')
            ->name('equipment-models.destroy');
    });
});

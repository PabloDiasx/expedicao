<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentModelController;
use App\Http\Controllers\ExpeditionController;
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
        Route::get('/equipamentos', [EquipmentController::class, 'index'])->name('equipments.index');
        Route::get('/equipamentos/{equipment}', [EquipmentController::class, 'show'])
            ->whereNumber('equipment')
            ->name('equipments.show');
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
    });

    // Admin only — manage models
    Route::middleware('role:admin')->group(function (): void {
        Route::get('/modelos-equipamentos', [EquipmentModelController::class, 'index'])->name('equipment-models.index');
        Route::post('/modelos-equipamentos', [EquipmentModelController::class, 'store'])->name('equipment-models.store');
        Route::delete('/modelos-equipamentos/{model}', [EquipmentModelController::class, 'destroy'])
            ->whereNumber('model')
            ->name('equipment-models.destroy');
    });
});

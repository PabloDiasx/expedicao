<?php

use App\Models\Tenant;
use App\Support\Nomus\NomusInvoiceSyncService;
use App\Support\Nomus\NomusSalesSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('nomus:sync-invoices {--tenant=} {--full}', function () {
    $tenantSlug = trim((string) $this->option('tenant'));
    $fullSync = (bool) $this->option('full');

    $tenantsQuery = Tenant::query()->where('is_active', true);
    if ($tenantSlug !== '') {
        $tenantsQuery->where('slug', $tenantSlug);
    }

    $tenants = $tenantsQuery->get();
    if ($tenants->isEmpty()) {
        $this->warn('Nenhum tenant ativo encontrado para sincronizar notas fiscais.');

        return 1;
    }

    /** @var NomusInvoiceSyncService $syncService */
    $syncService = app(NomusInvoiceSyncService::class);
    $hasErrors = false;

    foreach ($tenants as $tenant) {
        try {
            $result = $syncService->syncTenant($tenant, $fullSync);
            $this->info(sprintf(
                '[%s] Processadas: %d | Novas: %d | Atualizadas: %d | Paginas: %d',
                $tenant->slug,
                $result['total_processed'],
                $result['created'],
                $result['updated'],
                $result['pages'],
            ));
        } catch (\Throwable $exception) {
            $hasErrors = true;
            $this->error(sprintf(
                '[%s] Erro na sincronizacao: %s',
                $tenant->slug,
                $exception->getMessage()
            ));
        }
    }

    return $hasErrors ? 1 : 0;
})->purpose('Sincroniza periodicamente as notas fiscais da API Nomus');

Artisan::command('nomus:sync-sales-orders {--tenant=} {--full}', function () {
    $tenantSlug = trim((string) $this->option('tenant'));
    $fullSync = (bool) $this->option('full');

    $tenantsQuery = Tenant::query()->where('is_active', true);
    if ($tenantSlug !== '') {
        $tenantsQuery->where('slug', $tenantSlug);
    }

    $tenants = $tenantsQuery->get();
    if ($tenants->isEmpty()) {
        $this->warn('Nenhum tenant ativo encontrado para sincronizar pedidos de venda.');

        return 1;
    }

    /** @var NomusSalesSyncService $syncService */
    $syncService = app(NomusSalesSyncService::class);
    $hasErrors = false;

    foreach ($tenants as $tenant) {
        try {
            $result = $syncService->syncOrdersTenant($tenant, $fullSync);
            $this->info(sprintf(
                '[%s] Pedidos processados: %d | Novos: %d | Atualizados: %d | Paginas: %d',
                $tenant->slug,
                $result['total_processed'],
                $result['created'],
                $result['updated'],
                $result['pages'],
            ));
        } catch (\Throwable $exception) {
            $hasErrors = true;
            $this->error(sprintf(
                '[%s] Erro na sincronizacao de pedidos: %s',
                $tenant->slug,
                $exception->getMessage()
            ));
        }
    }

    return $hasErrors ? 1 : 0;
})->purpose('Sincroniza periodicamente os pedidos de venda da API Nomus');

Artisan::command('nomus:sync-sales-products {--tenant=} {--full}', function () {
    $tenantSlug = trim((string) $this->option('tenant'));
    $fullSync = (bool) $this->option('full');

    $tenantsQuery = Tenant::query()->where('is_active', true);
    if ($tenantSlug !== '') {
        $tenantsQuery->where('slug', $tenantSlug);
    }

    $tenants = $tenantsQuery->get();
    if ($tenants->isEmpty()) {
        $this->warn('Nenhum tenant ativo encontrado para sincronizar produtos de pedidos.');

        return 1;
    }

    /** @var NomusSalesSyncService $syncService */
    $syncService = app(NomusSalesSyncService::class);
    $hasErrors = false;

    foreach ($tenants as $tenant) {
        try {
            $result = $syncService->syncProductsTenant($tenant, $fullSync);
            $this->info(sprintf(
                '[%s] Produtos processados: %d | Novos: %d | Atualizados: %d | Paginas: %d',
                $tenant->slug,
                $result['total_processed'],
                $result['created'],
                $result['updated'],
                $result['pages'],
            ));
        } catch (\Throwable $exception) {
            $hasErrors = true;
            $this->error(sprintf(
                '[%s] Erro na sincronizacao de produtos: %s',
                $tenant->slug,
                $exception->getMessage()
            ));
        }
    }

    return $hasErrors ? 1 : 0;
})->purpose('Sincroniza periodicamente o cache local de produtos da API Nomus');

Artisan::command('nomus:sync-sales-bom {--tenant=} {--full}', function () {
    $tenantSlug = trim((string) $this->option('tenant'));
    $fullSync = (bool) $this->option('full');

    $tenantsQuery = Tenant::query()->where('is_active', true);
    if ($tenantSlug !== '') {
        $tenantsQuery->where('slug', $tenantSlug);
    }

    $tenants = $tenantsQuery->get();
    if ($tenants->isEmpty()) {
        $this->warn('Nenhum tenant ativo encontrado para sincronizar BOM de pedidos.');

        return 1;
    }

    /** @var NomusSalesSyncService $syncService */
    $syncService = app(NomusSalesSyncService::class);
    $hasErrors = false;

    foreach ($tenants as $tenant) {
        try {
            $result = $syncService->syncBomTenant($tenant, $fullSync);
            $this->info(sprintf(
                '[%s] Componentes BOM processados: %d | Novos: %d | Atualizados: %d | Paginas: %d',
                $tenant->slug,
                $result['total_processed'],
                $result['created'],
                $result['updated'],
                $result['pages'],
            ));
        } catch (\Throwable $exception) {
            $hasErrors = true;
            $this->error(sprintf(
                '[%s] Erro na sincronizacao da BOM: %s',
                $tenant->slug,
                $exception->getMessage()
            ));
        }
    }

    return $hasErrors ? 1 : 0;
})->purpose('Sincroniza periodicamente a lista de materiais dos produtos de pedidos');

$syncMinutes = max(1, min(59, (int) config('services.nomus.sync_minutes', 5)));
$syncCron = '*/'.$syncMinutes.' * * * *';

Schedule::command('nomus:sync-invoices')
    ->cron($syncCron)
    ->withoutOverlapping()
    ->name('nomus-sync-invoices');

Schedule::command('nomus:sync-sales-orders')
    ->cron($syncCron)
    ->withoutOverlapping()
    ->name('nomus-sync-sales-orders');

Schedule::command('nomus:sync-sales-products')
    ->cron($syncCron)
    ->withoutOverlapping()
    ->name('nomus-sync-sales-products');

Schedule::command('nomus:sync-sales-bom')
    ->cron($syncCron)
    ->withoutOverlapping()
    ->name('nomus-sync-sales-bom');

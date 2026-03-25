<?php

namespace App\Http\Middleware;

use App\Models\InvoiceSyncState;
use App\Models\Tenant;
use App\Support\Nomus\NomusInvoiceSyncService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AutoSyncNomus
{
    /**
     * Sync interval in minutes. Only triggers if last sync was older than this.
     */
    private const SYNC_INTERVAL_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        // Only run on GET requests (don't slow down form submissions)
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        // Only run for authenticated users
        if (! auth()->check()) {
            return $next($request);
        }

        try {
            $this->syncIfDue();
        } catch (Throwable $e) {
            // Never block the request — just log and continue
            Log::warning('[AutoSyncNomus] Erro na sincronizacao automatica: ' . $e->getMessage());
        }

        return $next($request);
    }

    private function syncIfDue(): void
    {
        $tenants = Tenant::query()->where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $state = InvoiceSyncState::query()
                ->where('tenant_id', $tenant->id)
                ->first();

            $lastRun = $state?->last_run_at;

            if ($lastRun && $lastRun->diffInMinutes(now()) < self::SYNC_INTERVAL_MINUTES) {
                continue;
            }

            /** @var NomusInvoiceSyncService $syncService */
            $syncService = app(NomusInvoiceSyncService::class);
            $syncService->syncTenant($tenant);
        }
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\InvoiceSyncState;
use App\Models\Tenant;
use App\Support\Invoices\InvoiceSerialLookupService;
use App\Support\Nomus\NomusInvoiceSyncService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AutoSyncNomus
{
    private const SYNC_INTERVAL_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! auth()->check()) {
            return $next($request);
        }

        try {
            $this->syncIfDue();
        } catch (Throwable $e) {
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

            // Após sync, tentar vincular NFs a equipamentos sem NF
            $this->autoLinkInvoices((int) $tenant->id);
        }
    }

    /**
     * Find equipments without invoice and try to match them.
     */
    private function autoLinkInvoices(int $tenantId): void
    {
        $unlinked = DB::table('equipments')
            ->where('tenant_id', $tenantId)
            ->whereNull('entry_invoice_id')
            ->where('serial_number', 'like', '%.%.%')
            ->limit(50)
            ->get(['id', 'serial_number']);

        if ($unlinked->isEmpty()) {
            return;
        }

        /** @var InvoiceSerialLookupService $lookupService */
        $lookupService = app(InvoiceSerialLookupService::class);
        $now = now();

        foreach ($unlinked as $eq) {
            try {
                $result = $lookupService->findBySerial($tenantId, $eq->serial_number);

                if (($result['matched'] ?? false) !== true || ($result['multiple'] ?? false) === true) {
                    continue;
                }

                DB::table('equipments')
                    ->where('id', $eq->id)
                    ->update([
                        'entry_invoice_id' => $result['invoice_id'],
                        'entry_invoice_external_id' => $result['invoice_external_id'],
                        'entry_invoice_number' => $result['invoice_number'],
                        'entry_customer_name' => $result['customer_name'],
                        'entry_destination' => $result['destination'],
                        'entry_invoice_linked_at' => $now,
                        'updated_at' => $now,
                    ]);

                \App\Support\Webhooks\WebhookDispatcher::dispatch($tenantId, 'nf_vinculada', [
                    'serial_number' => $eq->serial_number,
                    'invoice_number' => $result['invoice_number'],
                    'customer_name' => $result['customer_name'],
                    'destination' => $result['destination'],
                    'timestamp' => $now->toIso8601String(),
                ]);
            } catch (Throwable $e) {
                Log::warning('[AutoSyncNomus] Erro ao vincular NF ao equipamento ' . $eq->serial_number . ': ' . $e->getMessage());
            }
        }
    }
}

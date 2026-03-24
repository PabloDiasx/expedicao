<?php

namespace App\Http\Controllers;

use App\Support\Operations\MontagemPlanningService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenantContext,
        MontagemPlanningService $montagemPlanningService
    ): View {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        return $this->renderMontagemDashboard($request, (int) $tenant->id, $montagemPlanningService);
    }

    public function store(
        Request $request,
        TenantContext $tenantContext,
        MontagemPlanningService $montagemPlanningService
    ): RedirectResponse {
        $tenant = $tenantContext->tenant();
        if (! $tenant) {
            return back()->withErrors([
                'barcode' => 'Tenant nao identificado.',
            ]);
        }

        return $this->storeMontagemScan($request, (int) $tenant->id, $montagemPlanningService);
    }

    private function renderMontagemDashboard(
        Request $request,
        int $tenantId,
        MontagemPlanningService $montagemPlanningService
    ): View {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'due_from' => ['nullable', 'date'],
            'due_until' => ['nullable', 'date'],
        ]);

        $defaultHorizonDays = max(1, min(180, (int) config('operations.montagem_default_horizon_days', 30)));
        $dueFromRaw = trim((string) ($validated['due_from'] ?? ''));
        $dueUntilRaw = trim((string) ($validated['due_until'] ?? ''));
        $dueFrom = $dueFromRaw === ''
            ? now()->toImmutable()->startOfDay()
            : CarbonImmutable::parse($dueFromRaw, config('app.timezone', 'America/Sao_Paulo'));
        $dueUntil = $dueUntilRaw === ''
            ? now()->toImmutable()->addDays($defaultHorizonDays)
            : CarbonImmutable::parse($dueUntilRaw, config('app.timezone', 'America/Sao_Paulo'));
        if ($dueFrom->gt($dueUntil)) {
            [$dueFrom, $dueUntil] = [$dueUntil, $dueFrom];
        }

        $search = trim((string) ($validated['q'] ?? ''));
        $dashboard = $montagemPlanningService->buildDashboard(
            tenantId: $tenantId,
            dueFrom: $dueFrom,
            dueUntil: $dueUntil,
            search: $search
        );

        return view('production.index', [
            'etapa' => 'montagem',
            'dueFrom' => $dueFrom,
            'dueUntil' => $dueUntil,
            'filters' => [
                'q' => $search,
                'due_from' => $dueFrom->format('Y-m-d'),
                'due_until' => $dueUntil->format('Y-m-d'),
            ],
            'equipmentRows' => $dashboard['equipment_rows'],
            'accessoryRows' => $dashboard['accessory_rows'],
            'recentScans' => $dashboard['recent_scans'],
            'unmappedRows' => $dashboard['unmapped_rows'],
        ]);
    }


    private function storeMontagemScan(
        Request $request,
        int $tenantId,
        MontagemPlanningService $montagemPlanningService
    ): RedirectResponse {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:120'],
            'due_from' => ['nullable', 'date'],
            'due_until' => ['nullable', 'date'],
            'device_identifier' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $montagemPlanningService->registerScan(
            tenantId: $tenantId,
            userId: auth()->id(),
            barcode: (string) $validated['barcode'],
            deviceIdentifier: $validated['device_identifier'] ?? null,
            notes: $validated['notes'] ?? null
        );

        $redirectRoute = $this->buildMontagemRedirectParams($validated);

        if (($result['result'] ?? null) === 'updated') {
            return redirect()->route('production.index', $redirectRoute)->with('swal', [
                'icon' => 'success',
                'title' => 'Baixa registrada',
                'text' => 'Equipamento '.$result['serial_number'].' baixado no pedido '.$result['order_code'].'.',
            ]);
        }

        if (($result['result'] ?? null) === 'no_pending_demand') {
            return redirect()->route('production.index', $redirectRoute)->with('swal', [
                'icon' => 'warning',
                'title' => 'Sem saldo pendente',
                'text' => $result['message'],
            ]);
        }

        if (($result['result'] ?? null) === 'duplicate_scan') {
            return redirect()->route('production.index', $redirectRoute)->with('swal', [
                'icon' => 'info',
                'title' => 'Leitura duplicada',
                'text' => $result['message'],
            ]);
        }

        return redirect()->route('production.index', $redirectRoute)->with('swal', [
            'icon' => 'error',
            'title' => 'Nao foi possivel registrar',
            'text' => $result['message'] ?? 'Falha ao registrar a baixa na montagem.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    private function buildMontagemRedirectParams(array $validated): array
    {
        $params = ['etapa' => 'montagem'];
        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $params['q'] = $search;
        }

        $dueUntil = trim((string) ($validated['due_until'] ?? ''));
        if ($dueUntil !== '') {
            $params['due_until'] = $dueUntil;
        }

        $dueFrom = trim((string) ($validated['due_from'] ?? ''));
        if ($dueFrom !== '') {
            $params['due_from'] = $dueFrom;
        }

        return $params;
    }
}

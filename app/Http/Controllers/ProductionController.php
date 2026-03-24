<?php

namespace App\Http\Controllers;

use App\Support\Operations\EquipmentStatusService;
use App\Support\Operations\MontagemPlanningService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

        $etapa = $this->resolveEtapa($request->query('etapa'));
        if ($etapa === 'montagem') {
            return $this->renderMontagemDashboard($request, (int) $tenant->id, $montagemPlanningService);
        }

        return $this->renderLegacyStage((int) $tenant->id, $etapa);
    }

    public function store(
        Request $request,
        TenantContext $tenantContext,
        EquipmentStatusService $statusService,
        MontagemPlanningService $montagemPlanningService
    ): RedirectResponse {
        $tenant = $tenantContext->tenant();
        if (! $tenant) {
            return back()->withErrors([
                'barcode' => 'Tenant nao identificado.',
            ]);
        }

        $etapa = $this->resolveEtapa($request->query('etapa'));
        if ($etapa === 'montagem') {
            return $this->storeMontagemScan($request, (int) $tenant->id, $montagemPlanningService);
        }

        return $this->storeLegacyStageScan($request, (int) $tenant->id, $statusService, $etapa);
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

    private function renderLegacyStage(int $tenantId, string $etapa): View
    {
        $statuses = DB::table('statuses')
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'color']);

        $sectors = DB::table('sectors')
            ->where('is_active', true)
            ->whereIn('code', ['producao', 'montagem', 'estoque'])
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $defaultStatus = $statuses->firstWhere('code', 'produzindo') ?? $statuses->first();
        $defaultSector = $sectors->firstWhere('code', 'producao') ?? $sectors->first();
        $defaultStatusId = $defaultStatus?->id;
        $defaultSectorId = $defaultSector?->id;

        $recentTransitions = DB::table('status_histories as sh')
            ->join('equipments as e', 'e.id', '=', 'sh.equipment_id')
            ->join('statuses as st', 'st.id', '=', 'sh.to_status_id')
            ->leftJoin('sectors as sec', 'sec.id', '=', 'sh.sector_id')
            ->leftJoin('users as u', 'u.id', '=', 'sh.user_id')
            ->where('sh.tenant_id', $tenantId)
            ->where('sh.event_source', 'scanner_producao')
            ->orderByDesc('sh.changed_at')
            ->limit(15)
            ->get([
                'e.serial_number',
                'e.barcode',
                'st.name as status_name',
                'st.color as status_color',
                'sec.name as sector_name',
                'u.name as user_name',
                'sh.changed_at',
                'sh.notes',
            ]);

        return view('production.stage', [
            'etapa' => $etapa,
            'statuses' => $statuses,
            'sectors' => $sectors,
            'defaultStatusId' => $defaultStatusId,
            'defaultSectorId' => $defaultSectorId,
            'recentTransitions' => $recentTransitions,
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

    private function storeLegacyStageScan(
        Request $request,
        int $tenantId,
        EquipmentStatusService $statusService,
        string $etapa
    ): RedirectResponse {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:120'],
            'status_id' => ['required', 'integer', Rule::exists('statuses', 'id')],
            'sector_id' => ['required', 'integer', Rule::exists('sectors', 'id')],
            'device_identifier' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $allowedStatusCodes = [
            'produzindo',
            'montado',
            'transferencia',
            'desmontado',
            'embalado',
            'finalizado',
            'liberado',
            'carregando',
            'carregado',
        ];

        $status = DB::table('statuses')
            ->where('id', (int) $validated['status_id'])
            ->first(['id', 'code', 'name']);

        if (! $status || ! in_array((string) $status->code, $allowedStatusCodes, true)) {
            return back()->withErrors([
                'status_id' => 'Status invalido para o setor de producao.',
            ])->withInput();
        }

        $sector = DB::table('sectors')
            ->where('id', (int) $validated['sector_id'])
            ->where('is_active', true)
            ->whereIn('code', ['producao', 'montagem', 'estoque'])
            ->first(['id']);

        if (! $sector) {
            return back()->withErrors([
                'sector_id' => 'Setor invalido para o fluxo de producao.',
            ])->withInput();
        }

        $result = $statusService->applyBarcodeTransition(
            tenantId: $tenantId,
            userId: auth()->id(),
            barcode: (string) $validated['barcode'],
            toStatusId: (int) $validated['status_id'],
            sectorId: (int) $validated['sector_id'],
            deviceIdentifier: $validated['device_identifier'] ?? null,
            notes: $validated['notes'] ?? null,
            eventSource: 'scanner_producao'
        );

        if ($result['result'] === 'not_found') {
            return back()->withInput()->with('swal', [
                'icon' => 'error',
                'title' => 'Codigo nao encontrado',
                'text' => 'Nenhum equipamento foi encontrado para o codigo informado.',
            ]);
        }

        if ($result['result'] === 'no_change') {
            return back()->with('swal', [
                'icon' => 'info',
                'title' => 'Sem alteracao',
                'text' => 'O equipamento '.$result['serial_number'].' ja estava com esse status.',
            ]);
        }

        if ($result['result'] !== 'updated') {
            return back()->withErrors([
                'status_id' => 'Nao foi possivel registrar a movimentacao.',
            ])->withInput();
        }

        return redirect()->route('production.index', ['etapa' => $etapa])->with('swal', [
            'icon' => 'success',
            'title' => 'Movimentacao registrada',
            'text' => 'Equipamento '.$result['serial_number'].' atualizado para '.$result['status_name'].'.',
        ]);
    }

    private function resolveEtapa(mixed $value): string
    {
        $etapa = Str::lower(trim((string) $value));
        if (! in_array($etapa, ['solda', 'pintura', 'montagem'], true)) {
            return 'montagem';
        }

        return $etapa;
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

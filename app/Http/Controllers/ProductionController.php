<?php

namespace App\Http\Controllers;

use App\Support\Operations\EquipmentStatusService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

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
            ->where('sh.tenant_id', $tenant->id)
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

        return view('production.index', [
            'statuses' => $statuses,
            'sectors' => $sectors,
            'defaultStatusId' => $defaultStatusId,
            'defaultSectorId' => $defaultSectorId,
            'recentTransitions' => $recentTransitions,
        ]);
    }

    public function store(
        Request $request,
        TenantContext $tenantContext,
        EquipmentStatusService $statusService
    ): RedirectResponse {
        $tenant = $tenantContext->tenant();
        if (! $tenant) {
            return back()->withErrors([
                'barcode' => 'Tenant nao identificado.',
            ]);
        }

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
            tenantId: (int) $tenant->id,
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

        return redirect()->route('production.index')->with('swal', [
            'icon' => 'success',
            'title' => 'Movimentacao registrada',
            'text' => 'Equipamento '.$result['serial_number'].' atualizado para '.$result['status_name'].'.',
        ]);
    }
}

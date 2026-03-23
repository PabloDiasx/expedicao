<?php

namespace App\Http\Controllers;

use App\Support\Operations\EquipmentStatusService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExpeditionController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $dispatchStatus = DB::table('statuses')
            ->where('code', 'carregado')
            ->first(['id', 'name', 'color']);

        $dispatchSector = DB::table('sectors')
            ->where('code', 'expedicao')
            ->where('is_active', true)
            ->first(['id', 'name']);

        $recentDispatches = DB::table('status_histories as sh')
            ->join('equipments as e', 'e.id', '=', 'sh.equipment_id')
            ->join('statuses as st', 'st.id', '=', 'sh.to_status_id')
            ->leftJoin('users as u', 'u.id', '=', 'sh.user_id')
            ->where('sh.tenant_id', $tenant->id)
            ->where('sh.event_source', 'scanner_expedicao')
            ->orderByDesc('sh.changed_at')
            ->limit(15)
            ->get([
                'e.serial_number',
                'e.barcode',
                'st.name as status_name',
                'st.color as status_color',
                'u.name as user_name',
                'sh.changed_at',
                'sh.notes',
            ]);

        return view('expedition.index', [
            'dispatchStatus' => $dispatchStatus,
            'dispatchSector' => $dispatchSector,
            'recentDispatches' => $recentDispatches,
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
            'device_identifier' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $dispatchStatusId = DB::table('statuses')
            ->where('code', 'carregado')
            ->value('id');

        $dispatchSectorId = DB::table('sectors')
            ->where('code', 'expedicao')
            ->where('is_active', true)
            ->value('id');

        if (! $dispatchStatusId || ! $dispatchSectorId) {
            return back()->withErrors([
                'barcode' => 'Configuracao de expedicao incompleta. Verifique status "Carregado" e setor padrao.',
            ])->withInput();
        }

        $result = $statusService->applyBarcodeTransition(
            tenantId: (int) $tenant->id,
            userId: auth()->id(),
            barcode: (string) $validated['barcode'],
            toStatusId: (int) $dispatchStatusId,
            sectorId: (int) $dispatchSectorId,
            deviceIdentifier: $validated['device_identifier'] ?? null,
            notes: $validated['notes'] ?? null,
            eventSource: 'scanner_expedicao'
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
                'title' => 'Ja carregado',
                'text' => 'Equipamento '.$result['serial_number'].' ja estava carregado.',
            ]);
        }

        if ($result['result'] !== 'updated') {
            return back()->withErrors([
                'barcode' => 'Nao foi possivel concluir a expedicao.',
            ])->withInput();
        }

        return redirect()->route('expedition.index')->with('swal', [
            'icon' => 'success',
            'title' => 'Expedicao concluida',
            'text' => 'Equipamento '.$result['serial_number'].' marcado como carregado.',
        ]);
    }
}

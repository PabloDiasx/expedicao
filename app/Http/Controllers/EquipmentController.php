<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EquipmentController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status_id' => ['nullable', 'integer'],
            'sector_id' => ['nullable', 'integer'],
        ]);

        $statuses = DB::table('statuses')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color']);

        $sectors = DB::table('sectors')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = DB::table('equipments as e')
            ->join('equipment_models as em', 'em.id', '=', 'e.equipment_model_id')
            ->join('statuses as st', 'st.id', '=', 'e.current_status_id')
            ->leftJoin('sectors as sec', 'sec.id', '=', 'e.current_sector_id')
            ->where('e.tenant_id', $tenant->id)
            ->select([
                'e.id',
                'e.serial_number',
                'e.barcode',
                'e.manufactured_at',
                'e.assembled_at',
                'e.entry_invoice_id',
                'e.entry_invoice_number',
                'e.entry_customer_name',
                'e.entry_destination',
                'e.entry_invoice_linked_at',
                'e.updated_at',
                'em.name as model_name',
                'st.id as status_id',
                'st.name as status_name',
                'st.color as status_color',
                'sec.id as sector_id',
                'sec.name as sector_name',
            ]);

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('e.serial_number', 'like', '%'.$search.'%')
                    ->orWhere('e.barcode', 'like', '%'.$search.'%')
                    ->orWhere('em.name', 'like', '%'.$search.'%')
                    ->orWhere('e.entry_customer_name', 'like', '%'.$search.'%')
                    ->orWhere('e.entry_invoice_number', 'like', '%'.$search.'%');
            });
        }

        if (! empty($validated['status_id'])) {
            $query->where('e.current_status_id', (int) $validated['status_id']);
        }

        if (! empty($validated['sector_id'])) {
            $query->where('e.current_sector_id', (int) $validated['sector_id']);
        }

        $equipments = $query
            ->orderByDesc('e.updated_at')
            ->limit(200)
            ->get();

        $statusSummary = DB::table('equipments as e')
            ->join('statuses as st', 'st.id', '=', 'e.current_status_id')
            ->where('e.tenant_id', $tenant->id)
            ->groupBy('st.id', 'st.name', 'st.color')
            ->orderBy('st.name')
            ->get([
                'st.name',
                'st.color',
                DB::raw('COUNT(*) as total'),
            ]);

        return view('equipments.index', [
            'equipments' => $equipments,
            'statuses' => $statuses,
            'sectors' => $sectors,
            'statusSummary' => $statusSummary,
            'filters' => [
                'q' => $search,
                'status_id' => isset($validated['status_id']) ? (string) $validated['status_id'] : '',
                'sector_id' => isset($validated['sector_id']) ? (string) $validated['sector_id'] : '',
            ],
        ]);
    }

    public function show(int $equipment, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $equipmentRow = DB::table('equipments as e')
            ->join('equipment_models as em', 'em.id', '=', 'e.equipment_model_id')
            ->join('statuses as st', 'st.id', '=', 'e.current_status_id')
            ->leftJoin('sectors as sec', 'sec.id', '=', 'e.current_sector_id')
            ->leftJoin('fiscal_invoices as fi', function ($join): void {
                $join->on('fi.id', '=', 'e.entry_invoice_id')
                    ->on('fi.tenant_id', '=', 'e.tenant_id');
            })
            ->where('e.tenant_id', $tenant->id)
            ->where('e.id', $equipment)
            ->first([
                'e.id',
                'e.serial_number',
                'e.barcode',
                'e.notes',
                'e.manufactured_at',
                'e.assembled_at',
                'e.updated_at',
                'e.entry_invoice_id',
                'e.entry_invoice_external_id',
                'e.entry_invoice_number',
                'e.entry_customer_name',
                'e.entry_destination',
                'e.entry_invoice_linked_at',
                'em.name as model_name',
                'st.name as status_name',
                'st.color as status_color',
                'sec.name as sector_name',
                'fi.nomus_updated_at as invoice_nomus_updated_at',
            ]);

        abort_unless($equipmentRow, 404);

        $recentTransitions = DB::table('status_histories as sh')
            ->join('statuses as st', 'st.id', '=', 'sh.to_status_id')
            ->leftJoin('sectors as sec', 'sec.id', '=', 'sh.sector_id')
            ->leftJoin('users as u', 'u.id', '=', 'sh.user_id')
            ->where('sh.tenant_id', $tenant->id)
            ->where('sh.equipment_id', $equipment)
            ->orderByDesc('sh.changed_at')
            ->limit(20)
            ->get([
                'sh.changed_at',
                'st.name as status_name',
                'st.color as status_color',
                'sec.name as sector_name',
                'u.name as user_name',
                'sh.notes',
            ]);

        return view('equipments.show', [
            'equipment' => $equipmentRow,
            'recentTransitions' => $recentTransitions,
        ]);
    }

    public function destroy(int $equipment, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $row = DB::table('equipments')
            ->where('tenant_id', $tenant->id)
            ->where('id', $equipment)
            ->first(['id', 'serial_number']);

        if (! $row) {
            return back()->with('swal', [
                'icon' => 'error',
                'title' => 'Erro',
                'text' => 'Equipamento nao encontrado.',
            ]);
        }

        try {
            DB::table('status_histories')->where('equipment_id', $row->id)->delete();
            DB::table('barcode_reads')->where('equipment_id', $row->id)->delete();
            DB::table('equipments')->where('id', $row->id)->delete();
        } catch (\Throwable $e) {
            return back()->with('swal', [
                'icon' => 'error',
                'title' => 'Erro ao remover',
                'text' => 'Nao foi possivel remover o equipamento. Ele possui registros vinculados.',
            ]);
        }

        return back()->with('swal', [
            'icon' => 'success',
            'title' => 'Removido',
            'text' => 'Equipamento ' . $row->serial_number . ' removido com sucesso.',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HistoricoController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'source' => ['nullable', 'string', 'max:30'],
        ]);

        $query = DB::table('status_histories as sh')
            ->join('equipments as e', 'e.id', '=', 'sh.equipment_id')
            ->join('statuses as st_to', 'st_to.id', '=', 'sh.to_status_id')
            ->leftJoin('statuses as st_from', 'st_from.id', '=', 'sh.from_status_id')
            ->leftJoin('sectors as sec', 'sec.id', '=', 'sh.sector_id')
            ->leftJoin('users as u', 'u.id', '=', 'sh.user_id')
            ->leftJoin('equipment_models as em', 'em.id', '=', 'e.equipment_model_id')
            ->where('sh.tenant_id', $tenant->id);

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('e.serial_number', 'like', '%' . $search . '%')
                    ->orWhere('e.barcode', 'like', '%' . $search . '%')
                    ->orWhere('em.name', 'like', '%' . $search . '%')
                    ->orWhere('u.name', 'like', '%' . $search . '%')
                    ->orWhere('sh.notes', 'like', '%' . $search . '%')
                    ->orWhere('e.entry_customer_name', 'like', '%' . $search . '%');
            });
        }

        if (! empty($validated['from'])) {
            $query->whereDate('sh.changed_at', '>=', Carbon::parse($validated['from'])->toDateString());
        }

        if (! empty($validated['to'])) {
            $query->whereDate('sh.changed_at', '<=', Carbon::parse($validated['to'])->toDateString());
        }

        if (! empty($validated['status_id'])) {
            $query->where('sh.to_status_id', (int) $validated['status_id']);
        }

        if (! empty($validated['user_id'])) {
            $query->where('sh.user_id', (int) $validated['user_id']);
        }

        $source = trim((string) ($validated['source'] ?? ''));
        if ($source !== '') {
            $query->where('sh.event_source', $source);
        }

        $histories = $query
            ->orderByDesc('sh.changed_at')
            ->limit(500)
            ->get([
                'sh.id',
                'sh.changed_at',
                'sh.event_source',
                'sh.notes',
                'e.id as equipment_id',
                'e.serial_number',
                'e.barcode',
                'e.entry_customer_name',
                'e.entry_invoice_number',
                'em.name as model_name',
                'st_from.name as from_status_name',
                'st_from.color as from_status_color',
                'st_to.name as to_status_name',
                'st_to.color as to_status_color',
                'sec.name as sector_name',
                'u.name as user_name',
            ]);

        $statuses = DB::table('statuses')->orderBy('sort_order')->get(['id', 'name']);
        $users = DB::table('users')
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $eventSources = DB::table('status_histories')
            ->where('tenant_id', $tenant->id)
            ->distinct()
            ->pluck('event_source')
            ->filter()
            ->sort()
            ->values();

        return view('historicos.index', [
            'histories' => $histories,
            'statuses' => $statuses,
            'users' => $users,
            'eventSources' => $eventSources,
            'filters' => [
                'q' => $search,
                'from' => $validated['from'] ?? '',
                'to' => $validated['to'] ?? '',
                'status_id' => isset($validated['status_id']) ? (string) $validated['status_id'] : '',
                'user_id' => isset($validated['user_id']) ? (string) $validated['user_id'] : '',
                'source' => $source,
            ],
        ]);
    }
}

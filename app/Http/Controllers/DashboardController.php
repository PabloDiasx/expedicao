<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->tenant();
        abort_unless($tenant, 404);
        $maxDays = 30;
        $startDate = now()->subDays($maxDays - 1)->startOfDay();

        $statusByDate = DB::table('status_histories')
            ->selectRaw('DATE(changed_at) as day, COUNT(*) as total')
            ->where('tenant_id', $tenant->id)
            ->where('changed_at', '>=', $startDate)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $chartSeries = [];
        for ($i = 0; $i < $maxDays; $i++) {
            $date = now()->subDays($maxDays - 1 - $i)->toDateString();
            $chartSeries[] = [
                'date' => $date,
                'label' => Carbon::parse($date)->format('d/m'),
                'total' => (int) ($statusByDate[$date] ?? 0),
            ];
        }

        return view('dashboard', [
            'tenant' => $tenant,
            'chartSeries' => $chartSeries,
        ]);
    }
}


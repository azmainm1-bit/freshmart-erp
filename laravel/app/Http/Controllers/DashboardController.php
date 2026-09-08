<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Shift;
use App\Support\BusinessDate;
use App\Support\Reporting\ManagementReport;
use App\Support\Reporting\ReportPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $financial = $request->user()->can('reports.view');
        $today = BusinessDate::today();
        $period = new ReportPeriod(now(config('erp.timezone'))->subDays(29)->format('Y-m-d'), $today);

        return Inertia::render('overview', [
            'summary' => $financial ? ManagementReport::summary(new ReportPeriod($today, $today)) : null,
            'trend' => $financial ? ManagementReport::trend($period) : [],
            'alerts' => $request->user()->can('inventory.view') ? ManagementReport::alerts() : null,
            'recentSales' => Sale::query()->when(! $request->user()->can('sales.view-all'), fn ($q) => $q->where('cashier_id', $request->user()->id))->with('cashier:id,name', 'customer:id,name')->orderByDesc('posted_at')->limit(6)->get(),
            'shift' => Shift::where('cashier_id', $request->user()->id)->where('status', 'open')->first(),
            'businessDate' => $today,
        ]);
    }
}

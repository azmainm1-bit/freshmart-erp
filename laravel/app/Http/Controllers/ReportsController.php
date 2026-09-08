<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use App\Support\Reporting\ManagementReport;
use App\Support\Reporting\ReportPeriod;
use App\Support\Reporting\ReportTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    private function filters(Request $request): array
    {
        Gate::authorize('reports.view');

        return $request->validate(['type' => ['nullable', Rule::in(ReportTable::TYPES)], 'location_id' => ['nullable', 'uuid', 'exists:locations,id'], 'cashier_id' => ['nullable', 'uuid', 'exists:users,id'], 'method' => ['nullable', 'in:cash,card,mobile,bank']]);
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $period = ReportPeriod::fromRequest($request);
        $type = $filters['type'] ?? 'sales';

        return Inertia::render('reports/index', ['summary' => ManagementReport::summary($period), 'rows' => ReportTable::query($type, $period, $filters)->orderBy(in_array($type, ['stock', 'products', 'categories', 'cashiers', 'customers', 'suppliers']) ? 'description' : 'date', 'desc')->paginate(25)->withQueryString(), 'types' => ReportTable::TYPES, 'filters' => [...$filters, 'type' => $type, 'from' => $period->from, 'to' => $period->to], 'locations' => Location::orderBy('name')->get(['id', 'name']), 'cashiers' => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['cashier', 'manager', 'admin']))->orderBy('name')->get(['id', 'name'])]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $period = ReportPeriod::fromRequest($request);
        $type = $filters['type'] ?? 'sales';
        $query = ReportTable::query($type, $period, $filters);
        abort_if((clone $query)->getCountForPagination() > 50000, 422, 'Narrow the report to 50,000 rows or fewer before exporting.');

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            $first = true;
            foreach ($query->orderBy('id')->cursor() as $row) {
                $data = (array) $row;
                if ($first) {
                    fputcsv($stream, array_keys($data), escape: '');
                    $first = false;
                }
                fputcsv($stream, array_map(function ($value) {
                    $value = (string) ($value ?? '');

                    return preg_match('/^[=+@\t\r\n-]/', $value) && ! is_numeric($value) ? "'".$value : $value;
                }, $data), escape: '');
            }
            if ($first) {
                fputcsv($stream, ['No records in the selected period'], escape: '');
            }
            fclose($stream);
        }, $type.'-'.$period->from.'-'.$period->to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

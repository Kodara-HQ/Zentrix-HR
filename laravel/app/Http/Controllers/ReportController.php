<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Services\Reports;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/** Report preview with CSV (opens in Excel) and PDF export. Ported from GET /reports/:name. */
class ReportController extends Controller
{
    public function __invoke(Request $request, Reports $reports)
    {
        $user = $request->user();
        $available = Reports::available($user);
        $requested = $request->query('report');
        abort_if($requested && ! array_key_exists($requested, $available), array_key_exists($requested, Reports::CATALOGUE) ? 403 : 404, 'This report is restricted.');
        $name = $requested ?: array_key_first($available);
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]) + ['from' => today()->subYear()->toDateString(), 'to' => today()->toDateString()];
        $report = $reports->build($user, $name, $filters);
        $file = $name.'-'.today()->toDateString();

        return match ($request->query('format')) {
            'csv' => response()->streamDownload(function () use ($report) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF"); // so Excel reads the cedi sign and accents correctly
                fputcsv($out, array_values($report['columns']));
                foreach ($report['rows'] as $row) {
                    fputcsv($out, array_map(fn ($key) => $row[$key] ?? '', array_keys($report['columns'])));
                }
                fclose($out);
            }, "{$file}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']),
            'pdf' => Pdf::loadView('pdf.report', ['report' => $report, 'company' => Company::query()->value('name') ?: 'Zentrix HR', 'filters' => $filters])
                ->setPaper('a4', 'landscape')->stream("{$file}.pdf"),
            default => view('reports.index', [
                'available' => $available, 'name' => $name, 'report' => $report, 'filters' => $filters,
                'departments' => Department::orderBy('name')->pluck('name', 'id')->all(),
            ]),
        };
    }
}

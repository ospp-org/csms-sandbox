<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\ConformanceReport;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

final class ReportExporter
{
    public function toPdf(ConformanceReport $report, Tenant $tenant): string
    {
        $html = view('exports.conformance-pdf', [
            'report' => $report,
            'tenant' => $tenant,
            'generatedAt' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        return $pdf->output();
    }

    public function toJson(ConformanceReport $report): string
    {
        return (string) json_encode([
            'protocol_version' => $report->protocolVersion,
            'generated_at' => now()->toIso8601String(),
            'score' => [
                'passed' => $report->passed,
                'failed' => $report->failed,
                'partial' => $report->partial,
                'not_tested' => $report->notTested,
                'total_tested' => $report->totalTested,
                'percentage' => $report->percentage,
            ],
            'categories' => $report->categories,
            'results' => $report->results,
        ], JSON_PRETTY_PRINT);
    }
}

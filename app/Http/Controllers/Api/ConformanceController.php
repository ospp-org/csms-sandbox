<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConformanceResult;
use App\Models\Tenant;

use App\Services\ConformanceService;
use App\Services\ReportExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ConformanceController extends Controller
{
    public function index(Request $request, ConformanceService $conformance): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $version = $tenant->protocol_version ?? '0.1.0';

        $report = $conformance->getReport($tenant->id, $version);

        return new JsonResponse([
            'protocol_version' => $report->protocolVersion,
            'score' => [
                'passed' => $report->passed,
                'failed' => $report->failed,
                'partial' => $report->partial,
                'not_tested' => $report->notTested,
                'total_tested' => $report->totalTested,
                'percentage' => $report->percentage,
            ],
            'categories' => $report->categories,
            'results' => $this->formatResults($report->results),
        ]);
    }

    public function show(Request $request, string $action, ConformanceService $conformance): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $version = $tenant->protocol_version ?? '0.1.0';

        $result = $conformance->getActionDetail($tenant->id, $version, $action);

        if ($result === null) {
            return new JsonResponse(['error' => 'NOT_FOUND', 'message' => "No result for action: {$action}"], 404);
        }

        return new JsonResponse($this->formatSingleResult($result));
    }

    public function reset(Request $request, ConformanceService $conformance): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $version = $tenant->protocol_version ?? '0.1.0';

        $count = $conformance->reset($tenant->id, $version);

        return new JsonResponse([
            'message' => 'Conformance results reset',
            'actions_reset' => $count,
        ]);
    }

    public function exportPdf(Request $request, ConformanceService $conformance, ReportExporter $exporter): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $version = $tenant->protocol_version ?? '0.1.0';

        $report = $conformance->getReport($tenant->id, $version);
        $pdf = $exporter->toPdf($report, $tenant);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="conformance-report.pdf"',
        ]);
    }

    public function exportJson(Request $request, ConformanceService $conformance, ReportExporter $exporter): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $version = $tenant->protocol_version ?? '0.1.0';

        $report = $conformance->getReport($tenant->id, $version);
        $json = $exporter->toJson($report);

        return new Response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="conformance-report.json"',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<int, array<string, mixed>>
     */
    private function formatResults(array $results): array
    {
        /** @var array<string, list<string>> $categoryMap */
        $categoryMap = config('conformance.categories');
        $actionToCategory = [];
        foreach ($categoryMap as $category => $actions) {
            foreach ($actions as $action) {
                $actionToCategory[$action] = $category;
            }
        }

        return array_map(function (array $result) use ($actionToCategory) {
            return [
                'action' => $result['action'],
                'category' => $actionToCategory[$result['action']] ?? 'unknown',
                'status' => $result['status'],
                'last_tested_at' => $result['last_tested_at'],
                'schema_valid' => $result['error_details'] === null,
                'error_details' => $result['error_details'],
                'behavior_checks' => $result['behavior_checks'],
            ];
        }, $results);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSingleResult(ConformanceResult $result): array
    {
        /** @var array<string, list<string>> $categoryMap */
        $categoryMap = config('conformance.categories');
        $category = 'unknown';
        foreach ($categoryMap as $cat => $actions) {
            if (in_array($result->action, $actions, true)) {
                $category = $cat;
                break;
            }
        }

        return [
            'action' => $result->action,
            'category' => $category,
            'status' => $result->status,
            'last_tested_at' => $result->last_tested_at,
            'schema_valid' => $result->error_details === null,
            'error_details' => $result->error_details,
            'behavior_checks' => $result->behavior_checks,
        ];
    }
}

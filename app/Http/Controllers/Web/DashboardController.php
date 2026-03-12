<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Models\Tenant;
use App\Services\CommandService;
use App\Services\ConformanceService;
use App\Services\MqttCredentialService;
use App\Services\SchemaValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function setup(Request $request, MqttCredentialService $mqttCredentials): View
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $station = $tenant->station;
        $mqttPassword = $mqttCredentials->getPlainPassword($station);

        return view('dashboard.setup', compact('station', 'mqttPassword'));
    }

    public function monitor(Request $request): View
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $station = $tenant->station;

        return view('dashboard.monitor', compact('station'));
    }

    public function commands(Request $request): View
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $station = $tenant->station;

        return view('dashboard.commands', compact('station'));
    }

    public function conformance(Request $request, ConformanceService $conformance): View
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $version = $tenant->protocol_version ?? '0.1.0';
        $report = $conformance->getReport($tenant->id, $version);

        // Build category map for display
        $categoryMap = config('conformance.categories');
        $actionToCategory = [];
        foreach ($categoryMap as $category => $actions) {
            foreach ($actions as $action) {
                $actionToCategory[$action] = $category;
            }
        }

        return view('dashboard.conformance', compact('report', 'actionToCategory'));
    }

    public function history(Request $request): View
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $query = MessageLog::where('tenant_id', $tenant->id);

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
        }
        if ($request->filled('valid')) {
            $query->where('schema_valid', $request->input('valid') === '1');
        }
        if ($request->filled('search')) {
            $query->where('message_id', 'like', '%' . $request->input('search') . '%');
        }

        $messages = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        return view('dashboard.history', compact('messages'));
    }

    public function settings(Request $request): View
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        return view('dashboard.settings', compact('tenant'));
    }

    public function updateSettings(Request $request, ConformanceService $conformance): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $validated = $request->validate([
            'validation_mode' => 'sometimes|in:strict,lenient',
            'protocol_version' => 'sometimes|in:0.1.0',
        ]);

        $oldVersion = $tenant->protocol_version;

        $tenant->update($validated);

        if (isset($validated['protocol_version']) && $validated['protocol_version'] !== $oldVersion) {
            $conformance->reset($tenant->id, $validated['protocol_version']);
        }

        return redirect('/dashboard/settings')->with('success', 'Settings updated.');
    }

    public function sendCommand(Request $request, string $action, CommandService $commandService): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $result = $commandService->send(
            tenantId: $tenant->id,
            action: $action,
            parameters: $request->all(),
        );

        if (! $result->success) {
            $status = match ($result->errorCode) {
                'STATION_NOT_CONNECTED' => 409,
                'NO_STATION' => 404,
                'VALIDATION_ERROR' => 422,
                default => 400,
            };

            $body = ['error' => $result->errorCode, 'message' => $result->errorText];
            if ($result->validationErrors !== []) {
                $body['validation_errors'] = $result->validationErrors;
            }

            return new JsonResponse($body, $status);
        }

        return new JsonResponse([
            'command_id' => $result->commandId,
            'message_id' => $result->messageId,
            'status' => 'sent',
        ], 202);
    }

    public function commandSchema(string $action, SchemaValidationService $schemaValidator): JsonResponse
    {
        $schema = $schemaValidator->getOutboundSchema($action);

        if ($schema === null) {
            return new JsonResponse(['error' => 'UNKNOWN_ACTION'], 404);
        }

        return new JsonResponse(['action' => $action, 'schema' => $schema]);
    }

    public function resetConformance(Request $request, ConformanceService $conformance): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $version = $tenant->protocol_version ?? '0.1.0';

        $conformance->reset($tenant->id, $version);

        return redirect('/dashboard/conformance')->with('success', 'Conformance results reset.');
    }
}

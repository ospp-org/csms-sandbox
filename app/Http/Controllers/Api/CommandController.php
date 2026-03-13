<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Services\CommandService;
use App\Services\SchemaValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CommandController extends Controller
{
    public function send(Request $request, string $action, CommandService $commandService): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $result = $commandService->send(
            tenantId: $tenant->id,
            action: $action,
            parameters: $request->except(['_token', '_method']),
        );

        if (! $result->success) {
            $status = match ($result->errorCode) {
                'STATION_NOT_CONNECTED' => 409,
                'NO_STATION' => 404,
                'VALIDATION_ERROR' => 422,
                default => 400,
            };

            $body = [
                'error' => $result->errorCode,
                'message' => $result->errorText,
            ];

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

    public function history(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $commands = CommandHistory::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $mapped = [];
        foreach ($commands as $cmd) {
            $mapped[] = [
                'id' => $cmd->id,
                'action' => $cmd->action,
                'message_id' => $cmd->message_id,
                'status' => $cmd->status,
                'payload' => $cmd->payload,
                'response_payload' => $cmd->response_payload,
                'response_received_at' => $cmd->response_received_at?->toIso8601String(),
                'created_at' => $cmd->created_at?->toIso8601String(),
            ];
        }

        return new JsonResponse(['commands' => $mapped]);
    }

    public function schema(string $action, SchemaValidationService $schemaValidator): JsonResponse
    {
        $schema = $schemaValidator->getOutboundSchema($action);

        if ($schema === null) {
            return new JsonResponse(['error' => 'UNKNOWN_ACTION', 'message' => "No schema for action: {$action}"], 404);
        }

        return new JsonResponse([
            'action' => $action,
            'schema' => $schema,
        ]);
    }
}

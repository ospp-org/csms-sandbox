<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MessageLog;

final class MessageLogService
{
    /**
     * @param array<string, mixed> $payload
     * @param array<int|string, mixed>|null $validationErrors
     */
    public function logInbound(
        string $tenantId,
        string $stationId,
        string $action,
        string $messageId,
        string $messageType,
        array $payload,
        bool $schemaValid,
        ?array $validationErrors = null,
        ?int $processingTimeMs = null,
    ): MessageLog {
        return MessageLog::create([
            'tenant_id' => $tenantId,
            'station_id' => $stationId,
            'direction' => 'inbound',
            'action' => $action,
            'message_id' => $messageId,
            'message_type' => $messageType,
            'payload' => $payload,
            'schema_valid' => $schemaValid,
            'validation_errors' => $validationErrors,
            'processing_time_ms' => $processingTimeMs,
            'created_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function logOutbound(
        string $tenantId,
        string $stationId,
        string $action,
        string $messageId,
        array $payload,
    ): MessageLog {
        return MessageLog::create([
            'tenant_id' => $tenantId,
            'station_id' => $stationId,
            'direction' => 'outbound',
            'action' => $action,
            'message_id' => $messageId,
            'message_type' => 'Response',
            'payload' => $payload,
            'schema_valid' => true,
            'created_at' => now(),
        ]);
    }
}

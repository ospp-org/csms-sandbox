<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Handlers\BootNotificationHandler;
use App\Handlers\CancelReservationResponseHandler;
use App\Handlers\CertificateInstallResponseHandler;
use App\Handlers\ChangeConfigurationResponseHandler;
use App\Handlers\DataTransferHandler;
use App\Handlers\GetConfigurationResponseHandler;
use App\Handlers\HeartbeatHandler;
use App\Handlers\MeterValuesHandler;
use App\Handlers\ReserveBayResponseHandler;
use App\Handlers\ResetResponseHandler;
use App\Handlers\SecurityEventHandler;
use App\Handlers\SetMaintenanceModeResponseHandler;
use App\Handlers\SignCertificateHandler;
use App\Handlers\StartServiceResponseHandler;
use App\Handlers\StatusNotificationHandler;
use App\Handlers\StopServiceResponseHandler;
use App\Handlers\TriggerCertificateRenewalResponseHandler;
use App\Handlers\TriggerMessageResponseHandler;
use App\Handlers\UpdateFirmwareResponseHandler;
use App\Handlers\UpdateServiceCatalogResponseHandler;
use App\Handlers\UploadDiagnosticsResponseHandler;
use App\Models\TenantStation;
use Illuminate\Support\Facades\Log;

final class MqttMessageDispatcher
{
    /** @var array<string, class-string<OsppHandler>> */
    private const HANDLER_MAP = [
        'BootNotification' => BootNotificationHandler::class,
        'Heartbeat' => HeartbeatHandler::class,
        'StatusNotification' => StatusNotificationHandler::class,
        'MeterValues' => MeterValuesHandler::class,
        'DataTransfer' => DataTransferHandler::class,
        'SecurityEvent' => SecurityEventHandler::class,
        'SignCertificate' => SignCertificateHandler::class,
        'StartServiceResponse' => StartServiceResponseHandler::class,
        'StopServiceResponse' => StopServiceResponseHandler::class,
        'ReserveBayResponse' => ReserveBayResponseHandler::class,
        'CancelReservationResponse' => CancelReservationResponseHandler::class,
        'ChangeConfigurationResponse' => ChangeConfigurationResponseHandler::class,
        'GetConfigurationResponse' => GetConfigurationResponseHandler::class,
        'ResetResponse' => ResetResponseHandler::class,
        'UpdateFirmwareResponse' => UpdateFirmwareResponseHandler::class,
        'UploadDiagnosticsResponse' => UploadDiagnosticsResponseHandler::class,
        'SetMaintenanceModeResponse' => SetMaintenanceModeResponseHandler::class,
        'TriggerMessageResponse' => TriggerMessageResponseHandler::class,
        'UpdateServiceCatalogResponse' => UpdateServiceCatalogResponseHandler::class,
        'CertificateInstallResponse' => CertificateInstallResponseHandler::class,
        'TriggerCertificateRenewalResponse' => TriggerCertificateRenewalResponseHandler::class,
    ];

    public function __construct(
        private readonly MessageLogService $messageLog,
        private readonly EmqxApiPublisher $publisher,
        private readonly SchemaValidationService $schemaValidator,
        private readonly ConformanceService $conformance,
    ) {}

    /**
     * @param array<string, mixed> $envelope
     */
    public function dispatch(string $stationId, array $envelope): void
    {
        $startTime = microtime(true);

        $action = $envelope['action'] ?? '';
        $messageId = $envelope['messageId'] ?? '';
        $messageType = $envelope['messageType'] ?? '';
        $protocolVersion = $envelope['protocolVersion'] ?? '';
        $payload = $envelope['payload'] ?? [];

        $station = TenantStation::where('station_id', $stationId)->first();
        if ($station === null) {
            Log::warning("Message from unknown station: {$stationId}");

            return;
        }

        $tenantId = $station->tenant_id;

        $envelopeErrors = $this->validateEnvelope($envelope);
        $schemaResult = $this->schemaValidator->validate($action, $messageType, is_array($payload) ? $payload : []);

        $schemaValid = $envelopeErrors === [] && $schemaResult->valid;
        $validationErrors = $envelopeErrors;

        if (! $schemaResult->valid && ! $schemaResult->skipped) {
            foreach ($schemaResult->errors as $error) {
                $validationErrors[] = ['field' => $error['path'], 'message' => $error['message']];
            }
        }

        $processingTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        $this->messageLog->logInbound(
            tenantId: $tenantId,
            stationId: $stationId,
            action: $action,
            messageId: $messageId,
            messageType: $messageType,
            payload: $envelope,
            schemaValid: $schemaValid,
            validationErrors: $schemaValid ? null : $validationErrors,
            processingTimeMs: $processingTimeMs,
        );

        $context = new HandlerContext(
            tenantId: $tenantId,
            stationId: $stationId,
            action: $action,
            messageId: $messageId,
            messageType: $messageType,
            payload: is_array($payload) ? $payload : [],
            envelope: $envelope,
            protocolVersion: $protocolVersion,
        );

        $this->conformance->evaluate($context, $schemaResult);

        $validationMode = $station->tenant->validation_mode ?? 'strict';

        if (! $schemaValid && $validationMode === 'strict') {
            $this->publishErrorResponse($stationId, $action, $messageId, $protocolVersion, $validationErrors);

            return;
        }

        $handlerClass = self::HANDLER_MAP[$action] ?? null;
        if ($handlerClass === null) {
            Log::info("No handler for action: {$action}");

            return;
        }

        /** @var OsppHandler $handler */
        $handler = app($handlerClass);
        $result = $handler->handle($context);

        if ($result->responsePayload !== []) {
            $this->publishResponse($stationId, $action, $messageId, $protocolVersion, $result);
        }
    }

    /**
     * @param array<string, mixed> $envelope
     * @return array<int, array{field: string, message: string}>
     */
    private function validateEnvelope(array $envelope): array
    {
        $errors = [];
        $required = ['action', 'messageId', 'messageType', 'source', 'protocolVersion', 'timestamp', 'payload'];

        foreach ($required as $field) {
            if (! isset($envelope[$field])) {
                $errors[] = ['field' => $field, 'message' => "Missing required field: {$field}"];
            }
        }

        if (isset($envelope['timestamp']) && ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', (string) $envelope['timestamp'])) {
            $errors[] = ['field' => 'timestamp', 'message' => 'Invalid timestamp format (expected yyyy-MM-ddTHH:mm:ss.SSSZ)'];
        }

        return $errors;
    }

    private function publishResponse(
        string $stationId,
        string $action,
        string $messageId,
        string $protocolVersion,
        HandlerResult $result,
    ): void {
        $responseEnvelope = [
            'action' => $action,
            'messageId' => $messageId,
            'messageType' => 'Response',
            'source' => 'CSMS',
            'protocolVersion' => $protocolVersion,
            'timestamp' => now()->format('Y-m-d\TH:i:s.v\Z'),
            'payload' => $result->responsePayload,
        ];

        $topic = config('mqtt.topic_prefix') . "/{$stationId}/" . config('mqtt.to_station_suffix');

        try {
            $this->publisher->publish($topic, json_encode($responseEnvelope, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            Log::error("Failed to publish response for {$action}: {$e->getMessage()}");

            return;
        }

        $station = TenantStation::where('station_id', $stationId)->first();
        if ($station !== null) {
            $this->messageLog->logOutbound(
                tenantId: $station->tenant_id,
                stationId: $stationId,
                action: $action,
                messageId: $messageId,
                payload: $responseEnvelope,
            );
        }
    }

    /**
     * @param array<int, array{field: string, message: string}> $errors
     */
    private function publishErrorResponse(
        string $stationId,
        string $action,
        string $messageId,
        string $protocolVersion,
        array $errors,
    ): void {
        $result = HandlerResult::rejected('1005', 'INVALID_MESSAGE_FORMAT');

        $responseEnvelope = [
            'action' => $action,
            'messageId' => $messageId,
            'messageType' => 'Response',
            'source' => 'CSMS',
            'protocolVersion' => $protocolVersion,
            'timestamp' => now()->format('Y-m-d\TH:i:s.v\Z'),
            'payload' => [
                'status' => 'Rejected',
                'errorCode' => $result->errorCode,
                'errorText' => $result->errorText,
                'details' => $errors,
            ],
        ];

        $topic = config('mqtt.topic_prefix') . "/{$stationId}/" . config('mqtt.to_station_suffix');

        try {
            $this->publisher->publish($topic, json_encode($responseEnvelope, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            Log::error("Failed to publish error response: {$e->getMessage()}");
        }
    }
}

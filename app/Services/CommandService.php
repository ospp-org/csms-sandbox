<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\CommandResult;
use App\Models\CommandHistory;
use App\Models\TenantStation;
use App\Services\StationStateService;

final class CommandService
{
    /** @var array<int, string> */
    private const VALID_ACTIONS = [
        'StartService', 'StopService', 'ReserveBay', 'CancelReservation',
        'ChangeConfiguration', 'GetConfiguration', 'Reset', 'UpdateFirmware',
        'GetDiagnostics', 'SetMaintenanceMode', 'TriggerMessage',
        'UpdateServiceCatalog', 'CertificateInstall', 'TriggerCertificateRenewal',
        'DataTransfer',
    ];

    public function __construct(
        private readonly EmqxApiPublisher $publisher,
        private readonly MessageLogService $messageLog,
        private readonly SchemaValidationService $schemaValidator,
        private readonly StationStateService $stationState,
    ) {}

    /**
     * @param array<string, mixed> $parameters
     */
    public function send(string $tenantId, string $action, array $parameters): CommandResult
    {
        if (! in_array($action, self::VALID_ACTIONS, true)) {
            return CommandResult::error('INVALID_ACTION', "Unknown command action: {$action}");
        }

        $station = TenantStation::where('tenant_id', $tenantId)->first();
        if ($station === null) {
            return CommandResult::error('NO_STATION', 'No station found for tenant');
        }

        if (! $station->is_connected) {
            return CommandResult::error('STATION_NOT_CONNECTED', 'Station is not connected');
        }

        $validation = $this->schemaValidator->validateOutbound($action, $parameters);
        if (! $validation->valid) {
            return CommandResult::validationError($validation->errors);
        }

        // Spec §1.2: reject StartService/ReserveBay on Unknown bay (3002 BAY_NOT_READY)
        if (in_array($action, ['StartService', 'ReserveBay'], true)) {
            $bayId = (string) ($parameters['bayId'] ?? '');
            if ($bayId !== '') {
                $bayNumber = $this->stationState->resolveBayNumber($station->station_id, $bayId);
                if ($bayNumber > 0) {
                    $bayStatus = $this->stationState->getBayStatus($station->station_id, $bayNumber);
                    if ($bayStatus === 'Unknown') {
                        return CommandResult::error('BAY_NOT_READY', 'Bay is in Unknown state — waiting for StatusNotification after boot');
                    }
                }
            }
        }

        $messageId = 'msg_' . bin2hex(random_bytes(16));

        $envelope = [
            'action' => $action,
            'messageId' => $messageId,
            'messageType' => 'Request',
            'source' => 'Server',
            'protocolVersion' => $station->protocol_version,
            'timestamp' => now()->format('Y-m-d\TH:i:s.v\Z'),
            'payload' => $parameters,
        ];

        $topic = config('mqtt.topic_prefix') . "/{$station->station_id}/" . config('mqtt.to_station_suffix');

        try {
            $this->publisher->publish($topic, json_encode($envelope, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            return CommandResult::error('PUBLISH_FAILED', $e->getMessage());
        }

        $this->messageLog->logOutbound(
            tenantId: $tenantId,
            stationId: $station->station_id,
            action: $action,
            messageId: $messageId,
            payload: $envelope,
        );

        $command = CommandHistory::create([
            'tenant_id' => $tenantId,
            'station_id' => $station->station_id,
            'action' => $action,
            'message_id' => $messageId,
            'payload' => $parameters,
            'status' => 'sent',
        ]);

        return CommandResult::sent($command->id, $messageId);
    }

    public function findPendingByMessageId(string $messageId): ?CommandHistory
    {
        return CommandHistory::where('message_id', $messageId)
            ->where('status', 'sent')
            ->first();
    }

    /**
     * @return array<int, string>
     */
    public static function validActions(): array
    {
        return self::VALID_ACTIONS;
    }
}

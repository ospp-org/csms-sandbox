<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Models\TenantStation;
use App\Services\StationStateService;

final class BootNotificationHandler implements OsppHandler
{
    public function __construct(
        private readonly StationStateService $stationState,
    ) {}

    public function handle(HandlerContext $context): HandlerResult
    {
        $station = TenantStation::with('tenant')
            ->where('station_id', $context->stationId)
            ->first();

        if ($station === null || $station->tenant === null) {
            return HandlerResult::responded([
                'status' => 'Rejected',
                'serverTime' => now()->format('Y-m-d\TH:i:s.v\Z'),
                'heartbeatIntervalSec' => 30,
                'retryInterval' => 30,
            ]);
        }

        // Simulator control: force reject for testing boot-rejected flow
        if ($station->force_boot_reject) {
            $station->update(['force_boot_reject' => false]);

            return HandlerResult::responded([
                'status' => 'Rejected',
                'serverTime' => now()->format('Y-m-d\TH:i:s.v\Z'),
                'heartbeatIntervalSec' => 30,
                'retryInterval' => 10,
            ]);
        }

        // Simulator control: force pending for testing boot-pending flow
        if ($station->force_boot_pending) {
            $retryInterval = $station->boot_retry_interval ?? 30;
            $station->update(['force_boot_pending' => false, 'boot_retry_interval' => null]);

            return HandlerResult::responded([
                'status' => 'Pending',
                'serverTime' => now()->format('Y-m-d\TH:i:s.v\Z'),
                'heartbeatIntervalSec' => 30,
                'retryInterval' => $retryInterval,
            ]);
        }

        $tenantVersion = $station->tenant->protocol_version
            ?? config('sandbox.default_protocol_version');

        if ($context->protocolVersion === '' || $context->protocolVersion !== $tenantVersion) {
            return HandlerResult::responded([
                'status' => 'Rejected',
                'serverTime' => now()->format('Y-m-d\TH:i:s.v\Z'),
                'heartbeatIntervalSec' => 30,
                'retryInterval' => 30,
                'supportedVersions' => [$tenantVersion],
            ]);
        }

        $bayCount = (int) ($context->payload['bayCount'] ?? 4);

        $this->stationState->resetState($context->stationId, $bayCount);
        $this->stationState->refreshConnection($context->stationId);
        $this->stationState->setLifecycle($context->stationId, 'online');

        $heartbeatInterval = $this->stationState->getHeartbeatInterval($context->stationId);

        $station->update([
            'is_connected' => true,
            'last_connected_at' => now(),
            'last_boot_at' => now(),
            'firmware_version' => $context->payload['firmwareVersion'] ?? null,
            'station_model' => $context->payload['stationModel'] ?? null,
            'station_vendor' => $context->payload['stationVendor'] ?? null,
            'bay_count' => $bayCount,
            'protocol_version' => $context->protocolVersion,
        ]);

        return HandlerResult::responded([
            'status' => 'Accepted',
            'serverTime' => now()->format('Y-m-d\TH:i:s.v\Z'),
            'heartbeatIntervalSec' => $heartbeatInterval,
        ]);
    }
}

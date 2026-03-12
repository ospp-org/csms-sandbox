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
        $bayCount = (int) ($context->payload['bayCount'] ?? 4);

        $this->stationState->resetState($context->stationId, $bayCount);
        $this->stationState->refreshConnection($context->stationId);
        $this->stationState->setLifecycle($context->stationId, 'online');

        $heartbeatInterval = $this->stationState->getHeartbeatInterval($context->stationId);

        TenantStation::where('station_id', $context->stationId)->update([
            'is_connected' => true,
            'last_connected_at' => now(),
            'last_boot_at' => now(),
            'firmware_version' => $context->payload['firmwareVersion'] ?? null,
            'station_model' => $context->payload['stationModel'] ?? null,
            'station_vendor' => $context->payload['stationVendor'] ?? null,
            'bay_count' => $bayCount,
        ]);

        return HandlerResult::accepted([
            'status' => 'Accepted',
            'serverTime' => now()->format('Y-m-d\TH:i:s.v\Z'),
            'heartbeatIntervalSec' => $heartbeatInterval,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Models\TenantStation;
use App\Services\StationStateService;

final class ConnectionLostHandler implements OsppHandler
{
    public function __construct(
        private readonly StationStateService $stationState,
    ) {}

    public function handle(HandlerContext $context): HandlerResult
    {
        $this->stationState->setLifecycle($context->stationId, 'offline');
        $this->stationState->resetBaysToUnknown($context->stationId);

        TenantStation::where('station_id', $context->stationId)
            ->update(['is_connected' => false]);

        return HandlerResult::acknowledged();
    }
}

<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Services\StationStateService;

final class HeartbeatHandler implements OsppHandler
{
    public function __construct(
        private readonly StationStateService $stationState,
    ) {}

    public function handle(HandlerContext $context): HandlerResult
    {
        $this->stationState->refreshConnection($context->stationId);
        $this->stationState->setLastHeartbeat($context->stationId, time());

        return HandlerResult::accepted([
            'serverTime' => now()->format('Y-m-d\TH:i:s.v\Z'),
        ]);
    }
}

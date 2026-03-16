<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Services\StationStateService;

final class MeterValuesHandler implements OsppHandler
{
    public function __construct(
        private readonly StationStateService $stationState,
    ) {}

    public function handle(HandlerContext $context): HandlerResult
    {
        $bayId = (string) ($context->payload['bayId'] ?? '');
        $timestamp = (string) ($context->payload['timestamp'] ?? '');

        if ($bayId !== '' && $timestamp !== '') {
            $ts = strtotime($timestamp);

            if ($ts !== false) {
                $this->stationState->setLastMeterTimestamp($context->stationId, $bayId, $ts);
            }
        }

        return HandlerResult::acknowledged();
    }
}

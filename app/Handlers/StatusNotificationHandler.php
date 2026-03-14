<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Services\StationStateService;

final class StatusNotificationHandler implements OsppHandler
{
    public function __construct(
        private readonly StationStateService $stationState,
    ) {}

    public function handle(HandlerContext $context): HandlerResult
    {
        $bayStatus = (string) ($context->payload['status'] ?? '');
        $bayNumber = (int) ($context->payload['bayNumber'] ?? 0);
        $bayId = (string) ($context->payload['bayId'] ?? '');

        if ($bayId !== '' && $bayNumber > 0) {
            $this->stationState->setBayIdMapping($context->stationId, $bayId, $bayNumber);
        }

        $this->stationState->setBayStatus($context->stationId, $bayNumber, $bayStatus);

        return HandlerResult::acknowledged();
    }
}

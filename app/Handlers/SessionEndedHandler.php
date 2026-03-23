<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Services\StationStateService;

final class SessionEndedHandler implements OsppHandler
{
    public function __construct(
        private readonly StationStateService $stationState,
    ) {}

    public function handle(HandlerContext $context): HandlerResult
    {
        $bayId = (string) ($context->payload['bayId'] ?? '');
        $reason = (string) ($context->payload['reason'] ?? '');

        if ($bayId !== '') {
            $bayNumber = $this->stationState->resolveBayNumber($context->stationId, $bayId);

            if ($bayNumber > 0) {
                $bayStatus = $reason === 'Fault' ? 'Faulted' : 'Finishing';
                $this->stationState->setBayStatus($context->stationId, $bayNumber, $bayStatus);
                $this->stationState->setBaySession($context->stationId, $bayNumber, null);
            }
        }

        return HandlerResult::acknowledged();
    }
}

<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Services\StationStateService;

final class TransactionEventHandler implements OsppHandler
{
    public function __construct(
        private readonly StationStateService $stationState,
    ) {}

    public function handle(HandlerContext $context): HandlerResult
    {
        $txCounter = (int) ($context->payload['txCounter'] ?? 0);

        if ($txCounter > 0) {
            $this->stationState->setLastTxCounter($context->stationId, $txCounter);
        }

        return HandlerResult::responded([
            'status' => 'Accepted',
        ]);
    }
}

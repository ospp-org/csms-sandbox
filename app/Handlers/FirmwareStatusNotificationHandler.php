<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Services\StationStateService;

final class FirmwareStatusNotificationHandler implements OsppHandler
{
    public function __construct(
        private readonly StationStateService $stationState,
    ) {}

    public function handle(HandlerContext $context): HandlerResult
    {
        $status = (string) ($context->payload['status'] ?? '');

        if ($status !== '') {
            $this->stationState->setFirmwareStatus($context->stationId, $status);
        }

        return HandlerResult::acknowledged();
    }
}

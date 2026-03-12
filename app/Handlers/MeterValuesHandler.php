<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;

final class MeterValuesHandler implements OsppHandler
{
    public function handle(HandlerContext $context): HandlerResult
    {
        // Sandbox acknowledgement only — no state changes required.
        // bayId, sessionId, and readings are available via $context->payload
        // if future processing is needed.

        return HandlerResult::acknowledged();
    }
}

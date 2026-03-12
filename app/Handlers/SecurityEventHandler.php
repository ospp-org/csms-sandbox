<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;

final class SecurityEventHandler implements OsppHandler
{
    public function handle(HandlerContext $context): HandlerResult
    {
        // Security events are logged by the message pipeline; no additional
        // state changes are required in the sandbox environment.

        return HandlerResult::acknowledged();
    }
}

<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;

final class FirmwareStatusNotificationHandler implements OsppHandler
{
    public function handle(HandlerContext $context): HandlerResult
    {
        // Sandbox: acknowledge firmware update progress notifications.
        // Firmware status is captured in the message log by the dispatcher.
        return HandlerResult::acknowledged();
    }
}

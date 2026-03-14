<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;

final class TransactionEventHandler implements OsppHandler
{
    public function handle(HandlerContext $context): HandlerResult
    {
        // Sandbox: accept all offline transaction reports.
        return HandlerResult::accepted([
            'status' => 'Accepted',
        ]);
    }
}

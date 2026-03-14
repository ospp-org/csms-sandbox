<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;

final class DataTransferHandler implements OsppHandler
{
    public function handle(HandlerContext $context): HandlerResult
    {
        return HandlerResult::accepted([
            'status' => 'Accepted',
        ]);
    }
}

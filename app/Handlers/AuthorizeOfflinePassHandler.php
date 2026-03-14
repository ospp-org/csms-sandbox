<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;

final class AuthorizeOfflinePassHandler implements OsppHandler
{
    public function handle(HandlerContext $context): HandlerResult
    {
        // Sandbox: always accept offline passes with default session parameters.
        $sessionId = 'sess_' . bin2hex(random_bytes(8));

        return HandlerResult::accepted([
            'status' => 'Accepted',
            'sessionId' => $sessionId,
            'durationSeconds' => 3600,
            'creditsAuthorized' => 0,
        ]);
    }
}

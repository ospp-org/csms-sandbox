<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Services\CommandService;

final class UpdateFirmwareResponseHandler implements OsppHandler
{
    public function __construct(
        private readonly CommandService $commandService,
    ) {}

    public function handle(HandlerContext $context): HandlerResult
    {
        $status = $context->payload['status'] ?? 'Unknown';
        $command = $this->commandService->findPendingByMessageId($context->messageId);

        if ($command !== null) {
            $command->update([
                'status' => 'responded',
                'response_payload' => $context->payload,
                'response_received_at' => now(),
            ]);
        }

        return HandlerResult::acknowledged();
    }
}

<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Services\CommandService;
use App\Services\StationStateService;

final class SetMaintenanceModeResponseHandler implements OsppHandler
{
    public function __construct(
        private readonly CommandService $commandService,
        private readonly StationStateService $stationState,
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

            if ($status === 'Accepted') {
                $commandPayload = $command->payload ?? [];
                $bayId = $commandPayload['bayId'] ?? null;

                if ($bayId !== null) {
                    $bayNumber = $this->stationState->resolveBayNumber($context->stationId, (string) $bayId);
                    $enabled = (bool) ($commandPayload['enabled'] ?? false);
                    $bayStatus = $enabled ? 'Unavailable' : 'Available';

                    if ($bayNumber > 0) {
                        $this->stationState->setBayStatus($context->stationId, $bayNumber, $bayStatus);
                    }
                }
            }
        }

        return HandlerResult::acknowledged();
    }
}

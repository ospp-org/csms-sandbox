<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Services\CommandService;
use App\Services\StationStateService;

final class CancelReservationResponseHandler implements OsppHandler
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
        }

        if ($status === 'Accepted' && $command !== null) {
            $bayId = (string) ($command->payload['bayId'] ?? '');
            $bayNumber = (int) preg_replace('/\D/', '', $bayId);

            $this->stationState->setBayStatus($context->stationId, $bayNumber, 'Available');
            $this->stationState->setBayReservation($context->stationId, $bayNumber, null);
        }

        return HandlerResult::acknowledged();
    }
}

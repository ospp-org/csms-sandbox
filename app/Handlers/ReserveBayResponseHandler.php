<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Services\CommandService;
use App\Services\StationStateService;

final class ReserveBayResponseHandler implements OsppHandler
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
            $reservationId = (string) ($command->payload['reservationId'] ?? '');

            $this->stationState->setBayStatus($context->stationId, $bayNumber, 'Reserved');
            $this->stationState->setBayReservation($context->stationId, $bayNumber, $reservationId ?: null);
        }

        return HandlerResult::acknowledged();
    }
}

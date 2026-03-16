<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Models\CommandHistory;
use App\Services\StationStateService;

final class ReservationExpiryRule implements ConformanceRule
{
    public function name(): string
    {
        return 'reservation_expiry';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'ReserveBay' || $context->messageType !== 'Response') {
            return new RuleResult(true, 'reservation_expiry');
        }

        $status = $context->payload['status'] ?? '';
        if ($status !== 'Accepted') {
            return new RuleResult(true, 'reservation_expiry');
        }

        $command = CommandHistory::where('station_id', $context->stationId)
            ->where('action', 'ReserveBay')
            ->where('message_id', $context->messageId)
            ->first();

        if ($command === null) {
            return new RuleResult(true, 'reservation_expiry');
        }

        $expirationTime = $command->payload['expirationTime'] ?? null;
        if ($expirationTime === null) {
            return new RuleResult(false, 'reservation_expiry', 'ReserveBay command missing expirationTime');
        }

        try {
            $expiry = new \DateTimeImmutable($expirationTime);
            if ($expiry <= new \DateTimeImmutable()) {
                return new RuleResult(false, 'reservation_expiry', 'Reservation expirationTime is in the past');
            }
        } catch (\Throwable) {
            return new RuleResult(false, 'reservation_expiry', 'Invalid expirationTime format');
        }

        return new RuleResult(true, 'reservation_expiry');
    }
}

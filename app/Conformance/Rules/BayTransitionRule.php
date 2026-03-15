<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

final class BayTransitionRule implements ConformanceRule
{
    /** @var array<string, list<string>> */
    private const VALID_TRANSITIONS = [
        'Unknown' => ['Available', 'Faulted', 'Unavailable'],
        'Available' => ['Reserved', 'Occupied', 'Faulted', 'Unavailable'],
        'Reserved' => ['Available', 'Occupied', 'Faulted', 'Unavailable'],
        'Occupied' => ['Finishing', 'Faulted', 'Unavailable'],
        'Finishing' => ['Available', 'Faulted', 'Unavailable'],
        'Faulted' => ['Available', 'Unavailable'],
        'Unavailable' => ['Available', 'Faulted'],
    ];

    public function name(): string
    {
        return 'bay_transition';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'StatusNotification') {
            return new RuleResult(true, 'bay_transition');
        }

        $newStatus = (string) ($context->payload['status'] ?? '');
        $bayNumber = (int) ($context->payload['bayNumber'] ?? 0);

        if ($bayNumber === 0) {
            return new RuleResult(true, 'bay_transition');
        }

        $currentStatus = $state->getBayStatus($context->stationId, $bayNumber);

        // Same state → same state is always valid (station re-confirming)
        if ($newStatus === $currentStatus) {
            return new RuleResult(true, 'bay_transition');
        }

        $allowed = self::VALID_TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            return new RuleResult(false, 'bay_transition',
                "Invalid transition: {$currentStatus} -> {$newStatus}");
        }

        return new RuleResult(true, 'bay_transition');
    }
}

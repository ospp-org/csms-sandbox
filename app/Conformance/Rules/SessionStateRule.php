<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

final class SessionStateRule implements ConformanceRule
{
    public function name(): string
    {
        return 'session_state';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'MeterValues') {
            return new RuleResult(true, 'session_state');
        }

        $bayId = (string) ($context->payload['bayId'] ?? '');

        if ($bayId === '') {
            return new RuleResult(false, 'session_state', 'Missing bayId');
        }

        $bayNumber = $state->resolveBayNumber($context->stationId, $bayId);

        if ($bayNumber === 0) {
            return new RuleResult(false, 'session_state', "Unknown bayId: {$bayId}");
        }

        $session = $state->getBaySession($context->stationId, $bayNumber);

        if ($session === null) {
            return new RuleResult(false, 'session_state',
                "MeterValues received but no active session on bay {$bayNumber}");
        }

        return new RuleResult(true, 'session_state');
    }
}

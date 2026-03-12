<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

final class SessionStateRule implements ConformanceRule
{
    /** @var list<string> */
    private const SESSION_ACTIONS = ['MeterValues', 'StopServiceResponse'];

    public function name(): string
    {
        return 'session_state';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if (! in_array($context->action, self::SESSION_ACTIONS, true)) {
            return new RuleResult(true, 'session_state');
        }

        $bayNumber = (int) ($context->payload['bayNumber'] ?? 0);

        if ($bayNumber === 0) {
            return new RuleResult(false, 'session_state', 'Missing bayNumber');
        }

        $session = $state->getBaySession($context->stationId, $bayNumber);

        if ($session === null) {
            return new RuleResult(false, 'session_state',
                "{$context->action} received but no active session on bay {$bayNumber}");
        }

        return new RuleResult(true, 'session_state');
    }
}

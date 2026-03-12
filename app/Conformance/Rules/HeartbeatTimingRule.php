<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

final class HeartbeatTimingRule implements ConformanceRule
{
    public function name(): string
    {
        return 'heartbeat_timing';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'Heartbeat') {
            return new RuleResult(true, 'heartbeat_timing');
        }

        $lastHeartbeat = $state->getLastHeartbeat($context->stationId);

        if ($lastHeartbeat === null) {
            return new RuleResult(true, 'heartbeat_timing');
        }

        $interval = $state->getHeartbeatInterval($context->stationId);
        $elapsed = time() - $lastHeartbeat;
        $tolerance = (float) config('conformance.heartbeat_drift_tolerance', 0.10);
        $drift = $interval * $tolerance;

        if ($elapsed < ($interval - $drift) || $elapsed > ($interval + $drift)) {
            return new RuleResult(false, 'heartbeat_timing',
                "Heartbeat after {$elapsed}s, expected {$interval}s (±" . (int) ($tolerance * 100) . '%)');
        }

        return new RuleResult(true, 'heartbeat_timing');
    }
}

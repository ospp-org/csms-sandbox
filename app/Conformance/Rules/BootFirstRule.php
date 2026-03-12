<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

final class BootFirstRule implements ConformanceRule
{
    public function name(): string
    {
        return 'boot_first';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action === 'BootNotification') {
            return new RuleResult(true, 'boot_first');
        }

        $lifecycle = $state->getLifecycle($context->stationId);

        if ($lifecycle === 'offline' || $lifecycle === 'booting') {
            return new RuleResult(false, 'boot_first',
                "Received {$context->action} before BootNotification");
        }

        return new RuleResult(true, 'boot_first');
    }
}

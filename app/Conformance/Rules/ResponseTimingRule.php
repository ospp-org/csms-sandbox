<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Models\CommandHistory;
use App\Services\StationStateService;

final class ResponseTimingRule implements ConformanceRule
{
    public function name(): string
    {
        return 'response_timing';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if (! str_ends_with($context->action, 'Response')) {
            return new RuleResult(true, 'response_timing');
        }

        $baseAction = str_replace('Response', '', $context->action);
        $timeout = (int) config('conformance.command_response_timeout', 30);

        $command = CommandHistory::where('station_id', $context->stationId)
            ->where('action', $baseAction)
            ->where('status', 'sent')
            ->orderByDesc('created_at')
            ->first();

        if ($command === null) {
            return new RuleResult(true, 'response_timing');
        }

        $elapsed = (int) abs(now()->diffInSeconds($command->created_at));

        if ($elapsed > $timeout) {
            return new RuleResult(false, 'response_timing',
                "Response after {$elapsed}s, limit is {$timeout}s");
        }

        return new RuleResult(true, 'response_timing');
    }
}

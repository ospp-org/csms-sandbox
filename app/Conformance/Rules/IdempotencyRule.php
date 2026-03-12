<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Models\MessageLog;
use App\Services\StationStateService;

final class IdempotencyRule implements ConformanceRule
{
    public function name(): string
    {
        return 'idempotency';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        $count = MessageLog::where('station_id', $context->stationId)
            ->where('message_id', $context->messageId)
            ->where('direction', 'inbound')
            ->count();

        if ($count > 1) {
            return new RuleResult(false, 'idempotency',
                "Duplicate messageId: {$context->messageId}");
        }

        return new RuleResult(true, 'idempotency');
    }
}

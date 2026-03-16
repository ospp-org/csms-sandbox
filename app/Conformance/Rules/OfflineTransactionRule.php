<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

final class OfflineTransactionRule implements ConformanceRule
{
    public function name(): string
    {
        return 'offline_transaction';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'TransactionEvent') {
            return new RuleResult(true, 'offline_transaction');
        }

        $txCounter = (int) ($context->payload['txCounter'] ?? 0);
        $lastTxCounter = $state->getLastTxCounter($context->stationId);

        if ($txCounter <= $lastTxCounter) {
            return new RuleResult(false, 'offline_transaction',
                "Duplicate or out-of-order txCounter: received {$txCounter}, last was {$lastTxCounter}");
        }

        if ($txCounter > $lastTxCounter + 1) {
            return new RuleResult(false, 'offline_transaction',
                "Gap in txCounter sequence: received {$txCounter}, expected " . ($lastTxCounter + 1));
        }

        $state->setLastTxCounter($context->stationId, $txCounter);

        return new RuleResult(true, 'offline_transaction');
    }
}

<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

final class MeterValuesFrequencyRule implements ConformanceRule
{
    public function name(): string
    {
        return 'meter_values_frequency';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'MeterValues') {
            return new RuleResult(true, 'meter_values_frequency');
        }

        $bayId = (string) ($context->payload['bayId'] ?? '');
        $values = $context->payload['values'] ?? [];
        $timestamp = (string) ($context->payload['timestamp'] ?? '');

        // Check all values are non-negative
        if (is_array($values)) {
            foreach ($values as $value) {
                $numericValue = is_array($value)
                    ? (float) ($value['value'] ?? 0)
                    : (float) $value;

                if ($numericValue < 0) {
                    return new RuleResult(false, 'meter_values_frequency',
                        'Negative meter value detected: ' . $numericValue);
                }
            }
        }

        // Check timestamp ordering
        if ($bayId !== '' && $timestamp !== '') {
            $currentTs = strtotime($timestamp);

            if ($currentTs === false) {
                return new RuleResult(false, 'meter_values_frequency',
                    'Invalid timestamp format: ' . $timestamp);
            }

            $lastTs = $state->getLastMeterTimestamp($context->stationId, $bayId);

            if ($lastTs !== null && $currentTs <= $lastTs) {
                return new RuleResult(false, 'meter_values_frequency',
                    'Meter values timestamp is not after previous: ' . $timestamp);
            }

            $state->setLastMeterTimestamp($context->stationId, $bayId, $currentTs);
        }

        return new RuleResult(true, 'meter_values_frequency');
    }
}

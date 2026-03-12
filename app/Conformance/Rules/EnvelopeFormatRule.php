<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

final class EnvelopeFormatRule implements ConformanceRule
{
    /** @var list<string> */
    private const REQUIRED_FIELDS = [
        'action', 'messageId', 'messageType', 'source', 'protocolVersion', 'timestamp', 'payload',
    ];

    public function name(): string
    {
        return 'envelope_format';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        $errors = [];
        $envelope = $context->envelope;

        foreach (self::REQUIRED_FIELDS as $field) {
            if (! isset($envelope[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        if (isset($envelope['timestamp'])
            && ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', (string) $envelope['timestamp'])) {
            $errors[] = "Invalid timestamp format: {$envelope['timestamp']} (expected yyyy-MM-ddTHH:mm:ss.SSSZ)";
        }

        if ($errors !== []) {
            return new RuleResult(false, 'envelope_format', implode('; ', $errors));
        }

        return new RuleResult(true, 'envelope_format');
    }
}

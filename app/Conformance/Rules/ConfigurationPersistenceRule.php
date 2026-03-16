<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

final class ConfigurationPersistenceRule implements ConformanceRule
{
    public function name(): string
    {
        return 'configuration_persistence';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'ChangeConfiguration' || $context->messageType !== 'Response') {
            return new RuleResult(true, 'configuration_persistence');
        }

        $results = $context->payload['results'] ?? [];
        if (! is_array($results) || $results === []) {
            return new RuleResult(false, 'configuration_persistence', 'Missing or empty results array');
        }

        foreach ($results as $result) {
            $status = $result['status'] ?? '';

            if (in_array($status, ['Rejected', 'NotSupported'], true)) {
                if (! isset($result['errorCode']) || ! isset($result['errorText'])) {
                    $key = $result['key'] ?? 'unknown';

                    return new RuleResult(false, 'configuration_persistence',
                        "Key '{$key}' status '{$status}' missing errorCode/errorText");
                }
            }
        }

        return new RuleResult(true, 'configuration_persistence');
    }
}

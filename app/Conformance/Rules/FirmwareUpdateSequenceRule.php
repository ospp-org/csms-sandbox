<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

final class FirmwareUpdateSequenceRule implements ConformanceRule
{
    /** @var array<string, list<string>> */
    private const VALID_TRANSITIONS = [
        '' => ['Downloading', 'Failed'],
        'Downloading' => ['Downloaded', 'Failed'],
        'Downloaded' => ['Installing', 'Failed'],
        'Installing' => ['Installed', 'Failed'],
        'Installed' => ['Downloading', ''],
        'Failed' => ['Downloading', ''],
    ];

    public function name(): string
    {
        return 'firmware_update_sequence';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'FirmwareStatusNotification') {
            return new RuleResult(true, 'firmware_update_sequence');
        }

        $newStatus = (string) ($context->payload['status'] ?? '');
        $previousStatus = $state->getFirmwareStatus($context->stationId);

        $allowed = self::VALID_TRANSITIONS[$previousStatus] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            return new RuleResult(false, 'firmware_update_sequence',
                "Invalid firmware transition: {$previousStatus} -> {$newStatus}");
        }

        $state->setFirmwareStatus($context->stationId, $newStatus);

        return new RuleResult(true, 'firmware_update_sequence');
    }
}

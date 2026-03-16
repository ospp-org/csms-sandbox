<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

final class DiagnosticsUploadRule implements ConformanceRule
{
    /** @var array<string, list<string>> */
    private const VALID_TRANSITIONS = [
        '' => ['Collecting', 'Failed'],
        'Collecting' => ['Uploading', 'Failed'],
        'Uploading' => ['Uploaded', 'Failed'],
        'Uploaded' => ['Collecting', ''],
        'Failed' => ['Collecting', ''],
    ];

    public function name(): string
    {
        return 'diagnostics_upload';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'DiagnosticsNotification') {
            return new RuleResult(true, 'diagnostics_upload');
        }

        $newStatus = (string) ($context->payload['status'] ?? '');
        $previousStatus = $state->getDiagnosticsStatus($context->stationId);

        $allowed = self::VALID_TRANSITIONS[$previousStatus] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            return new RuleResult(false, 'diagnostics_upload',
                "Invalid diagnostics transition: {$previousStatus} -> {$newStatus}");
        }

        $state->setDiagnosticsStatus($context->stationId, $newStatus);

        return new RuleResult(true, 'diagnostics_upload');
    }
}

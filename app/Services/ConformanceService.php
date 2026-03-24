<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ConformanceRule;
use App\Dto\ConformanceReport;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Dto\ValidationResult;
use App\Models\ConformanceResult;
use App\Conformance\Rules\BayTransitionRule;
use App\Conformance\Rules\BootFirstRule;
use App\Conformance\Rules\CertificateExpiryRule;
use App\Conformance\Rules\ConfigurationPersistenceRule;
use App\Conformance\Rules\DiagnosticsUploadRule;
use App\Conformance\Rules\EnvelopeFormatRule;
use App\Conformance\Rules\FirmwareUpdateSequenceRule;
use App\Conformance\Rules\HeartbeatTimingRule;
use App\Conformance\Rules\IdempotencyRule;
use App\Conformance\Rules\MeterValuesFrequencyRule;
use App\Conformance\Rules\OfflineTransactionRule;
use App\Conformance\Rules\ReservationExpiryRule;
use App\Conformance\Rules\ResponseTimingRule;
use App\Conformance\Rules\SessionStateRule;

final class ConformanceService
{
    /** @var list<ConformanceRule> */
    private array $rules;

    public function __construct(
        private readonly StationStateService $stationState,
    ) {
        $this->rules = [
            new BootFirstRule(),
            new HeartbeatTimingRule(),
            new SessionStateRule(),
            new BayTransitionRule(),
            new ResponseTimingRule(),
            new IdempotencyRule(),
            new EnvelopeFormatRule(),
            new FirmwareUpdateSequenceRule(),
            new DiagnosticsUploadRule(),
            new OfflineTransactionRule(),
            new MeterValuesFrequencyRule(),
            new ReservationExpiryRule(),
            new CertificateExpiryRule(),
            new ConfigurationPersistenceRule(),
        ];
    }

    /**
     * Run all behavior rules and record the conformance result.
     *
     * @return list<RuleResult>
     */
    public function evaluate(HandlerContext $context, ValidationResult $schemaResult): array
    {
        $behaviorResults = [];
        foreach ($this->rules as $rule) {
            $behaviorResults[] = $rule->check($context, $this->stationState);
        }

        $this->recordResult(
            $context->tenantId,
            $context->stationId,
            $context->protocolVersion,
            $context->action,
            $schemaResult,
            $behaviorResults,
            $context->payload,
        );

        return $behaviorResults;
    }

    /**
     * @param list<RuleResult> $behaviorResults
     * @param array<string, mixed> $payload
     */
    public function recordResult(
        string $tenantId,
        string $stationId,
        string $protocolVersion,
        string $action,
        ValidationResult $schemaResult,
        array $behaviorResults,
        array $payload,
    ): void {
        $schemaValid = $schemaResult->valid;
        $behaviorsPassed = collect($behaviorResults)->every(fn (RuleResult $r) => $r->passed);

        $status = match (true) {
            $schemaValid && $behaviorsPassed => 'passed',
            ! $schemaValid => 'failed',
            default => 'partial',
        };

        ConformanceResult::updateOrCreate(
            [
                'station_id' => $stationId,
                'protocol_version' => $protocolVersion,
                'action' => $action,
            ],
            [
                'tenant_id' => $tenantId,
                'status' => $status,
                'last_tested_at' => now(),
                'last_payload' => $payload,
                'error_details' => $schemaValid ? null : $schemaResult->errors,
                'behavior_checks' => collect($behaviorResults)->map(fn (RuleResult $r) => [
                    'rule' => $r->rule,
                    'passed' => $r->passed,
                    'detail' => $r->detail,
                ])->toArray(),
            ]
        );
    }

    public function getReport(string $stationId, string $protocolVersion): ConformanceReport
    {
        $results = ConformanceResult::where('station_id', $stationId)
            ->where('protocol_version', $protocolVersion)
            ->get();

        // Ensure all config actions are represented (as not_tested if missing from DB)
        /** @var list<string> $configActions */
        $configActions = config('conformance.actions', []);
        $existingActions = $results->pluck('action')->toArray();

        foreach ($configActions as $action) {
            if (! in_array($action, $existingActions, true)) {
                $results->push(new ConformanceResult([
                    'station_id' => $stationId,
                    'protocol_version' => $protocolVersion,
                    'action' => $action,
                    'status' => 'not_tested',
                    'last_tested_at' => null,
                    'last_payload' => null,
                    'error_details' => null,
                    'behavior_checks' => null,
                ]));
            }
        }

        // Sort: recently tested first, not_tested last
        $sorted = $results->sortBy(function (ConformanceResult $r) {
            if ($r->last_tested_at === null) {
                return '9999-99-99';
            }

            return (string) $r->last_tested_at;
        }, SORT_REGULAR, true)->values();

        return new ConformanceReport($sorted, $protocolVersion);
    }

    public function getActionDetail(string $stationId, string $protocolVersion, string $action): ?ConformanceResult
    {
        return ConformanceResult::where('station_id', $stationId)
            ->where('protocol_version', $protocolVersion)
            ->where('action', $action)
            ->first();
    }

    public function reset(string $stationId, string $protocolVersion): int
    {
        return ConformanceResult::where('station_id', $stationId)
            ->where('protocol_version', $protocolVersion)
            ->update([
                'status' => 'not_tested',
                'last_tested_at' => null,
                'last_payload' => null,
                'error_details' => null,
                'behavior_checks' => null,
            ]);
    }
}

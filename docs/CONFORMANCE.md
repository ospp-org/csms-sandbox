# CSMS Sandbox — Conformance Engine

---

## Overview

The conformance engine validates firmware implementations against the OSPP v0.1.0 specification. Two layers:

1. **Schema validation** — JSON Schema from `ospp/protocol` SDK
2. **Behavior validation** — protocol rules beyond schema (timing, state machine, sequencing)

---

## Schema Validation

### SchemaValidationService

Validates every inbound message against JSON Schema from `ospp/protocol` SDK.

```php
final class SchemaValidationService
{
    public function validate(string $action, string $messageType, array $payload, string $protocolVersion): ValidationResult
    {
        $schemaPath = SchemaPath::forMessage($action, $messageType, $protocolVersion);

        if ($schemaPath === null) {
            return ValidationResult::skipped("No schema for {$action}/{$messageType}");
        }

        $validator = new OpisValidator();
        $result = $validator->validate(
            json_decode(json_encode($payload)),
            file_get_contents($schemaPath)
        );

        if ($result->isValid()) {
            return ValidationResult::valid();
        }

        $errors = [];
        foreach ($result->error()->subErrors() as $error) {
            $errors[] = [
                'path' => $error->dataPointer(),
                'message' => $error->message(),
                'keyword' => $error->keyword(),
            ];
        }

        return ValidationResult::invalid($errors);
    }
}
```

### Validation Modes

**Strict mode** (default):
```php
if (!$validationResult->isValid()) {
    // Reject: send error response, do NOT process
    $response = [
        'status' => 'Rejected',
        'errorCode' => '1005',
        'errorText' => 'INVALID_MESSAGE_FORMAT',
        'details' => $validationResult->errors,
    ];
    // Log as failed in conformance
    $this->conformance->recordFailed($action, $validationResult->errors);
    return $response;
}
```

**Lenient mode:**
```php
if (!$validationResult->isValid()) {
    // Log validation errors but process anyway
    $this->conformance->recordFailed($action, $validationResult->errors);
    // Continue to handler — process best-effort
}
```

---

## Behavior Validation

### Rules

Each rule implements:

```php
interface ConformanceRule
{
    public function name(): string;
    public function check(HandlerContext $context, StationStateService $state): RuleResult;
}

final readonly class RuleResult
{
    public function __construct(
        public bool $passed,
        public string $rule,
        public ?string $detail = null,
    ) {}
}
```

### Rule: BootFirstRule

**What:** BootNotification must be the first message after MQTT connect.

```php
final class BootFirstRule implements ConformanceRule
{
    public function name(): string { return 'boot_first'; }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'BootNotification') {
            // Check if station has booted
            $lifecycle = $state->getLifecycle($context->stationId);
            if ($lifecycle === 'offline' || $lifecycle === 'booting') {
                return new RuleResult(false, 'boot_first',
                    "Received {$context->action} before BootNotification");
            }
        }

        return new RuleResult(true, 'boot_first');
    }
}
```

### Rule: HeartbeatTimingRule

**What:** Heartbeat must be sent within ±10% of configured interval.

```php
final class HeartbeatTimingRule implements ConformanceRule
{
    public function name(): string { return 'heartbeat_timing'; }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'Heartbeat') {
            return new RuleResult(true, 'heartbeat_timing');
        }

        $lastHeartbeat = $state->getLastHeartbeat($context->stationId);
        if ($lastHeartbeat === null) {
            // First heartbeat after boot — can't check timing
            return new RuleResult(true, 'heartbeat_timing');
        }

        $interval = $state->getHeartbeatInterval($context->stationId);
        $elapsed = time() - $lastHeartbeat;
        $tolerance = $interval * 0.1; // ±10%

        if ($elapsed < ($interval - $tolerance) || $elapsed > ($interval + $tolerance)) {
            return new RuleResult(false, 'heartbeat_timing',
                "Heartbeat after {$elapsed}s, expected {$interval}s (±10%)");
        }

        return new RuleResult(true, 'heartbeat_timing');
    }
}
```

### Rule: SessionStateRule

**What:** No MeterValues or StopServiceResponse without an active session.

```php
final class SessionStateRule implements ConformanceRule
{
    public function name(): string { return 'session_state'; }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        $sessionActions = ['MeterValues', 'StopServiceResponse'];

        if (!in_array($context->action, $sessionActions, true)) {
            return new RuleResult(true, 'session_state');
        }

        $bayId = $context->payload['bayId'] ?? null;
        if ($bayId === null) {
            return new RuleResult(false, 'session_state', 'Missing bayId');
        }

        // Extract bay number from bayId
        $bayNumber = $this->resolveBayNumber($context->stationId, $bayId, $state);
        if ($bayNumber === null) {
            return new RuleResult(false, 'session_state', "Unknown bay: {$bayId}");
        }

        $session = $state->getBaySession($context->stationId, $bayNumber);
        if ($session === null) {
            return new RuleResult(false, 'session_state',
                "{$context->action} received but no active session on bay {$bayId}");
        }

        return new RuleResult(true, 'session_state');
    }
}
```

### Rule: BayTransitionRule

**What:** StatusNotification must follow valid bay state transitions.

```php
final class BayTransitionRule implements ConformanceRule
{
    private const VALID_TRANSITIONS = [
        'Unknown' => ['Available', 'Faulted', 'Unavailable'],
        'Available' => ['Reserved', 'Occupied', 'Faulted', 'Unavailable'],
        'Reserved' => ['Available', 'Occupied', 'Faulted', 'Unavailable'],
        'Occupied' => ['Finishing', 'Faulted', 'Unavailable'],
        'Finishing' => ['Available', 'Faulted', 'Unavailable'],
        'Faulted' => ['Available', 'Unavailable'],
        'Unavailable' => ['Available', 'Faulted'],
    ];

    public function name(): string { return 'bay_transition'; }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'StatusNotification') {
            return new RuleResult(true, 'bay_transition');
        }

        $newStatus = $context->payload['bayStatus'] ?? '';
        $bayId = $context->payload['bayId'] ?? '';
        $bayNumber = $this->resolveBayNumber($context->stationId, $bayId, $state);

        if ($bayNumber === null) {
            return new RuleResult(true, 'bay_transition'); // Can't check unknown bay
        }

        $currentStatus = $state->getBayStatus($context->stationId, $bayNumber);
        $allowed = self::VALID_TRANSITIONS[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            return new RuleResult(false, 'bay_transition',
                "Invalid transition: {$currentStatus} → {$newStatus}");
        }

        return new RuleResult(true, 'bay_transition');
    }
}
```

### Rule: ResponseTimingRule

**What:** Station must respond to commands within 30 seconds.

Checked by `CommandService` timeout scheduler, not by a handler rule. When timeout fires:

```php
// Scheduled: check every 10 seconds
$pendingCommands = CommandHistory::where('status', 'sent')
    ->where('created_at', '<', now()->subSeconds(30))
    ->get();

foreach ($pendingCommands as $command) {
    $command->update(['status' => 'timeout']);

    $this->conformance->recordFailed(
        $command->action . 'Response',
        [['rule' => 'response_timing', 'detail' => 'No response within 30 seconds']]
    );
}
```

### Rule: IdempotencyRule

**What:** Duplicate messageId should not cause double processing.

```php
final class IdempotencyRule implements ConformanceRule
{
    public function name(): string { return 'idempotency'; }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        // Check if this messageId was already processed
        $exists = MessageLog::where('station_id', $context->stationId)
            ->where('message_id', $context->messageId)
            ->where('direction', 'inbound')
            ->where('id', '<', DB::raw('(SELECT MAX(id) FROM message_log)'))
            ->exists();

        if ($exists) {
            return new RuleResult(false, 'idempotency',
                "Duplicate messageId: {$context->messageId}");
        }

        return new RuleResult(true, 'idempotency');
    }
}
```

### Rule: EnvelopeFormatRule

**What:** All required envelope fields present with correct format.

```php
final class EnvelopeFormatRule implements ConformanceRule
{
    public function name(): string { return 'envelope_format'; }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        $errors = [];
        $envelope = $context->envelope;

        $required = ['action', 'messageId', 'messageType', 'source', 'protocolVersion', 'timestamp', 'payload'];
        foreach ($required as $field) {
            if (!isset($envelope[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Timestamp format: yyyy-MM-ddTHH:mm:ss.SSSZ
        if (isset($envelope['timestamp'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $envelope['timestamp'])) {
                $errors[] = "Invalid timestamp format: {$envelope['timestamp']} (expected yyyy-MM-ddTHH:mm:ss.SSSZ)";
            }
        }

        if ($errors !== []) {
            return new RuleResult(false, 'envelope_format', implode('; ', $errors));
        }

        return new RuleResult(true, 'envelope_format');
    }
}
```

---

## Conformance Service

Aggregates validation results per action.

```php
final class ConformanceService
{
    public function recordResult(
        string $tenantId,
        string $protocolVersion,
        string $action,
        ValidationResult $schemaResult,
        array $behaviorResults,
        array $payload,
    ): void {
        $schemaValid = $schemaResult->isValid();
        $behaviorsPassed = collect($behaviorResults)->every(fn (RuleResult $r) => $r->passed);

        $status = match (true) {
            $schemaValid && $behaviorsPassed => 'passed',
            !$schemaValid => 'failed',
            default => 'partial', // schema OK but behavior issues
        };

        ConformanceResult::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'protocol_version' => $protocolVersion,
                'action' => $action,
            ],
            [
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

    public function getReport(string $tenantId, string $protocolVersion): ConformanceReport
    {
        $results = ConformanceResult::where('tenant_id', $tenantId)
            ->where('protocol_version', $protocolVersion)
            ->get();

        return new ConformanceReport($results, $protocolVersion);
    }

    public function reset(string $tenantId, string $protocolVersion): int
    {
        return ConformanceResult::where('tenant_id', $tenantId)
            ->where('protocol_version', $protocolVersion)
            ->update(['status' => 'not_tested', 'last_tested_at' => null, 'error_details' => null, 'behavior_checks' => null]);
    }
}
```

---

## Scoring

```php
final readonly class ConformanceReport
{
    public int $passed;
    public int $failed;
    public int $partial;
    public int $notTested;
    public int $totalTested;
    public float $percentage;
    public array $categories;
    public array $results;

    public function __construct(Collection $results, string $protocolVersion)
    {
        $this->passed = $results->where('status', 'passed')->count();
        $this->failed = $results->where('status', 'failed')->count();
        $this->partial = $results->where('status', 'partial')->count();
        $this->notTested = $results->where('status', 'not_tested')->count();
        $this->totalTested = $this->passed + $this->failed + $this->partial;
        $this->percentage = $this->totalTested > 0
            ? round(($this->passed / $this->totalTested) * 100, 1)
            : 0;

        $this->categories = $this->calculateCategories($results);
        $this->results = $results->toArray();
    }

    private function calculateCategories(Collection $results): array
    {
        $categoryMap = [
            'core' => ['BootNotification', 'Heartbeat', 'StatusNotification', 'DataTransfer'],
            'sessions' => ['MeterValues', 'StartServiceResponse', 'StopServiceResponse'],
            'reservations' => ['ReserveBayResponse', 'CancelReservationResponse'],
            'device_management' => [
                'ChangeConfigurationResponse', 'GetConfigurationResponse',
                'ResetResponse', 'UpdateFirmwareResponse', 'UploadDiagnosticsResponse',
                'SetMaintenanceModeResponse', 'TriggerMessageResponse',
                'UpdateServiceCatalogResponse',
            ],
            'security' => [
                'SecurityEvent', 'SignCertificate',
                'CertificateInstallResponse', 'TriggerCertificateRenewalResponse',
            ],
        ];

        $categories = [];
        foreach ($categoryMap as $category => $actions) {
            $categoryResults = $results->whereIn('action', $actions);
            $passed = $categoryResults->where('status', 'passed')->count();
            $total = $categoryResults->where('status', '!=', 'not_tested')->count();

            $categories[$category] = [
                'passed' => $passed,
                'total' => $total,
                'percentage' => $total > 0 ? round(($passed / $total) * 100, 1) : 0,
            ];
        }

        return $categories;
    }
}
```

---

## Export

### PDF Export

Using `barryvdh/laravel-dompdf`:

```php
final class ReportExporter
{
    public function toPdf(ConformanceReport $report, Tenant $tenant): string
    {
        $html = view('exports.conformance-pdf', [
            'report' => $report,
            'tenant' => $tenant,
            'generatedAt' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html);
        return $pdf->output();
    }

    public function toJson(ConformanceReport $report): string
    {
        return json_encode([
            'protocol_version' => $report->protocolVersion,
            'generated_at' => now()->toIso8601String(),
            'score' => [
                'passed' => $report->passed,
                'failed' => $report->failed,
                'partial' => $report->partial,
                'not_tested' => $report->notTested,
                'percentage' => $report->percentage,
            ],
            'categories' => $report->categories,
            'results' => $report->results,
        ], JSON_PRETTY_PRINT);
    }
}
```

### PDF Template

Branded OSPP conformance report:
- Header: OSPP logo, "Protocol Conformance Report"
- Tenant info: name, email, station ID, test date
- Score summary: big number (81.8%), bar chart
- Category breakdown: table
- Per-message detail: status, errors, behavior checks
- Footer: "Generated by CSMS Sandbox — csms-sandbox.ospp-standard.org"

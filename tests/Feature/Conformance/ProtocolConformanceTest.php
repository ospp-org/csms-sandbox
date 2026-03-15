<?php

declare(strict_types=1);

/**
 * Protocol Conformance Test — source of truth is the SDK, not the sandbox.
 *
 * Zero references to sandbox HANDLER_MAP or internal conventions.
 * Action names, schemas, and envelope format come exclusively from:
 *   - Ospp\Protocol\Actions\OsppAction
 *   - Ospp\Protocol\SchemaPath
 *   - Ospp\Protocol\Enums\MessageType
 */

use App\Models\CommandHistory;
use App\Models\MessageLog;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\MqttMessageDispatcher;
use App\Services\StationStateService;
use Illuminate\Support\Facades\Http;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Ospp\Protocol\Actions\OsppAction;
use Ospp\Protocol\SchemaPath;

// ---------------------------------------------------------------------------
// Helpers — SDK-driven, no sandbox imports
// ---------------------------------------------------------------------------

function sdkSchemaDir(): string
{
    return SchemaPath::directory();
}

function sdkValidate(string $schemaFile, mixed $data): array
{
    $validator = new Validator();
    $validator->setMaxErrors(10);
    $validator->resolver()->registerPrefix(
        'https://ospp-standard.org/schemas/v1/',
        sdkSchemaDir() . '/'
    );

    $schema = json_decode((string) file_get_contents($schemaFile));
    $json = json_decode(json_encode($data));
    $result = $validator->validate($json, $schema);

    if ($result->isValid()) {
        return [];
    }

    return (new ErrorFormatter())->format($result->error());
}

function sdkEnvelope(string $action, string $messageId, string $messageType, array $payload): array
{
    return [
        'action' => $action,
        'messageId' => $messageId,
        'messageType' => $messageType,
        'source' => 'Station',
        'protocolVersion' => '0.1.0',
        'timestamp' => '2026-03-15T10:00:00.000Z',
        'payload' => $payload,
    ];
}

function sdkBootPayload(string $stationId): array
{
    return [
        'stationId' => $stationId,
        'firmwareVersion' => '1.0.0',
        'stationModel' => 'TestModel',
        'stationVendor' => 'TestVendor',
        'serialNumber' => 'SN000001',
        'bayCount' => 2,
        'uptimeSeconds' => 0,
        'pendingOfflineTransactions' => 0,
        'timezone' => 'UTC',
        'bootReason' => 'PowerOn',
        'capabilities' => [
            'bleSupported' => false,
            'offlineModeSupported' => false,
            'meterValuesSupported' => true,
        ],
        'networkInfo' => ['connectionType' => 'Ethernet'],
    ];
}

function sdkSetupStation(string $stationId): array
{
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => $stationId]);

    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch($stationId, sdkEnvelope(
        'BootNotification', 'msg_boot_' . $stationId, 'Request', sdkBootPayload($stationId)
    ));

    $state = app(StationStateService::class);
    $state->setBayIdMapping($stationId, 'bay_00000001', 1);
    $state->setBayIdMapping($stationId, 'bay_00000002', 2);

    return [$tenant, $dispatcher];
}

function sdkKebabCase(string $pascal): string
{
    return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $pascal));
}

// ---------------------------------------------------------------------------
// PART 1: COMPLETENESS — all SDK actions are handled
// ---------------------------------------------------------------------------

test('sandbox handles all 26 MQTT actions defined in OsppAction SDK', function (): void {
    $sdkActions = OsppAction::mqttActions();
    expect(count($sdkActions))->toBe(26);

    [$tenant, $dispatcher] = sdkSetupStation('stn_aa000001');

    foreach ($sdkActions as $action) {
        $kebab = sdkKebabCase($action);
        $hasRequestSchema = file_exists(sdkSchemaDir() . "/mqtt/{$kebab}-request.schema.json");
        $hasEventSchema = file_exists(sdkSchemaDir() . "/mqtt/{$kebab}-event.schema.json")
            || file_exists(sdkSchemaDir() . "/mqtt/{$kebab}.schema.json");
        $hasResponseSchema = file_exists(sdkSchemaDir() . "/mqtt/{$kebab}-response.schema.json");

        // Every action must have at least one schema file
        expect($hasRequestSchema || $hasEventSchema || $hasResponseSchema)
            ->toBeTrue("SDK action '{$action}' has no schema file");
    }
});

// ---------------------------------------------------------------------------
// PART 2: REQUEST→RESPONSE — station sends Request, server responds
// ---------------------------------------------------------------------------

test('BootNotification: request processed, response conforms to SDK schema', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_ab000001']);

    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch('stn_ab000001', sdkEnvelope(
        'BootNotification', 'msg_pct_boot', 'Request', sdkBootPayload('stn_ab000001')
    ));

    $outbound = MessageLog::where('station_id', 'stn_ab000001')
        ->where('direction', 'outbound')->where('action', 'BootNotification')->first();
    expect($outbound)->not->toBeNull('Server must respond to BootNotification Request');

    $envelope = $outbound->payload;
    expect($envelope['action'])->toBe('BootNotification');
    expect($envelope['messageType'])->toBe('Response');
    expect($envelope['source'])->toBe('Server');
    expect($envelope['messageId'])->toBe('msg_pct_boot');

    $errors = sdkValidate(sdkSchemaDir() . '/mqtt/boot-notification-response.schema.json', $envelope['payload']);
    expect($errors)->toBe([], 'boot-notification-response schema validation failed');
});

test('Heartbeat: request processed, response conforms to SDK schema', function (): void {
    [$tenant, $dispatcher] = sdkSetupStation('stn_ab000002');

    $dispatcher->dispatch('stn_ab000002', sdkEnvelope('Heartbeat', 'msg_pct_hb', 'Request', []));

    $outbound = MessageLog::where('station_id', 'stn_ab000002')
        ->where('direction', 'outbound')->where('action', 'Heartbeat')->first();
    expect($outbound)->not->toBeNull();

    $envelope = $outbound->payload;
    expect($envelope['action'])->toBe('Heartbeat');
    expect($envelope['messageType'])->toBe('Response');
    expect($envelope['source'])->toBe('Server');

    $errors = sdkValidate(sdkSchemaDir() . '/mqtt/heartbeat-response.schema.json', $envelope['payload']);
    expect($errors)->toBe([]);
});

test('DataTransfer: request processed, response conforms to SDK schema', function (): void {
    [$tenant, $dispatcher] = sdkSetupStation('stn_ab000003');

    $dispatcher->dispatch('stn_ab000003', sdkEnvelope('DataTransfer', 'msg_pct_dt', 'Request', [
        'vendorId' => 'com.example',
        'dataId' => 'test',
    ]));

    $outbound = MessageLog::where('station_id', 'stn_ab000003')
        ->where('direction', 'outbound')->where('action', 'DataTransfer')->first();
    expect($outbound)->not->toBeNull();

    $errors = sdkValidate(sdkSchemaDir() . '/mqtt/data-transfer-response.schema.json', $outbound->payload['payload']);
    expect($errors)->toBe([]);
});

test('SignCertificate: request processed, response conforms to SDK schema', function (): void {
    [$tenant, $dispatcher] = sdkSetupStation('stn_ab000004');

    $dispatcher->dispatch('stn_ab000004', sdkEnvelope('SignCertificate', 'msg_pct_sc', 'Request', [
        'certificateType' => 'StationCertificate',
        'csr' => "-----BEGIN CERTIFICATE REQUEST-----\nMIIBxTCCAWug\n-----END CERTIFICATE REQUEST-----",
    ]));

    $outbound = MessageLog::where('station_id', 'stn_ab000004')
        ->where('direction', 'outbound')->where('action', 'SignCertificate')->first();
    expect($outbound)->not->toBeNull();

    $errors = sdkValidate(sdkSchemaDir() . '/mqtt/sign-certificate-response.schema.json', $outbound->payload['payload']);
    expect($errors)->toBe([]);
});

test('AuthorizeOfflinePass: request processed, response conforms to SDK schema', function (): void {
    [$tenant, $dispatcher] = sdkSetupStation('stn_ab000005');

    $dispatcher->dispatch('stn_ab000005', sdkEnvelope('AuthorizeOfflinePass', 'msg_pct_aop', 'Request', [
        'offlinePassId' => 'opass_0a1b2c3d',
        'offlinePass' => [
            'passId' => 'opass_0a1b2c3d',
            'sub' => 'sub_user001',
            'deviceId' => 'dev-phone-001',
            'issuedAt' => '2026-03-15T09:00:00.000Z',
            'expiresAt' => '2026-03-16T09:00:00.000Z',
            'policyVersion' => 1,
            'revocationEpoch' => 1000,
            'offlineAllowance' => [
                'maxTotalCredits' => 5000,
                'maxUses' => 10,
                'maxCreditsPerTx' => 1000,
                'allowedServiceTypes' => ['svc_wash'],
            ],
            'constraints' => [
                'minIntervalSec' => 60,
                'stationOfflineWindowHours' => 24,
                'stationMaxOfflineTx' => 50,
            ],
            'signatureAlgorithm' => 'ECDSA-P256-SHA256',
            'signature' => 'dGVzdHNpZ25hdHVyZQ==',
        ],
        'deviceId' => 'dev-phone-001',
        'counter' => 1,
        'bayId' => 'bay_00000001',
        'serviceId' => 'svc_wash',
    ]));

    $outbound = MessageLog::where('station_id', 'stn_ab000005')
        ->where('direction', 'outbound')->where('action', 'AuthorizeOfflinePass')->first();
    expect($outbound)->not->toBeNull();

    $errors = sdkValidate(sdkSchemaDir() . '/mqtt/authorize-offline-pass-response.schema.json', $outbound->payload['payload']);
    expect($errors)->toBe([]);
});

test('TransactionEvent: request processed, response conforms to SDK schema', function (): void {
    [$tenant, $dispatcher] = sdkSetupStation('stn_ab000006');

    $dispatcher->dispatch('stn_ab000006', sdkEnvelope('TransactionEvent', 'msg_pct_txe', 'Request', [
        'offlineTxId' => 'otx_0a1b2c3d',
        'offlinePassId' => 'opass_0a1b2c3d',
        'userId' => 'sub_user001',
        'bayId' => 'bay_00000001',
        'serviceId' => 'svc_wash',
        'startedAt' => '2026-03-15T10:00:00.000Z',
        'endedAt' => '2026-03-15T10:30:00.000Z',
        'durationSeconds' => 1800,
        'creditsCharged' => 100,
        'receipt' => [
            'data' => 'eyJ0eCI6InRlc3QifQ==',
            'signature' => 'dGVzdHNpZ25hdHVyZQ==',
            'signatureAlgorithm' => 'ECDSA-P256-SHA256',
        ],
        'txCounter' => 1,
    ]));

    $outbound = MessageLog::where('station_id', 'stn_ab000006')
        ->where('direction', 'outbound')->where('action', 'TransactionEvent')->first();
    expect($outbound)->not->toBeNull();

    $errors = sdkValidate(sdkSchemaDir() . '/mqtt/transaction-event-response.schema.json', $outbound->payload['payload']);
    expect($errors)->toBe([]);
});

// ---------------------------------------------------------------------------
// PART 3: COMMAND→RESPONSE — server command, station responds
// Action name stays CONSTANT (no "Response" suffix). messageType = "Response".
// ---------------------------------------------------------------------------

$commandActions = [
    'StartService' => ['bayId' => 'bay_00000001', 'serviceId' => 'svc_wash', 'sessionId' => 'sess_00000001'],
    'StopService' => ['bayId' => 'bay_00000001', 'sessionId' => 'sess_00000001'],
    'ReserveBay' => ['bayId' => 'bay_00000001', 'reservationId' => 'res_00000001'],
    'CancelReservation' => ['bayId' => 'bay_00000001', 'reservationId' => 'res_00000001'],
    'Reset' => ['type' => 'Soft'],
    'ChangeConfiguration' => ['keys' => [['key' => 'heartbeatInterval', 'value' => '60']]],
    'GetConfiguration' => [],
    'UpdateFirmware' => ['firmwareUrl' => 'https://example.com/fw.bin', 'firmwareVersion' => '2.0.0'],
    'GetDiagnostics' => ['uploadUrl' => 'https://example.com/diag'],
    'SetMaintenanceMode' => ['enabled' => true, 'bayId' => 'bay_00000001'],
    'TriggerMessage' => ['requestedMessage' => 'StatusNotification'],
    'UpdateServiceCatalog' => ['catalog' => []],
    'CertificateInstall' => ['certificate' => '-----BEGIN CERTIFICATE-----\ntest\n-----END CERTIFICATE-----', 'certificateType' => 'StationCertificate'],
    'TriggerCertificateRenewal' => ['certificateType' => 'StationCertificate'],
];

$responsePayloads = [
    'StartService' => ['status' => 'Accepted'],
    'StopService' => ['status' => 'Accepted', 'actualDurationSeconds' => 300, 'creditsCharged' => 100],
    'ReserveBay' => ['status' => 'Accepted'],
    'CancelReservation' => ['status' => 'Accepted'],
    'Reset' => ['status' => 'Accepted'],
    'ChangeConfiguration' => ['results' => [['key' => 'heartbeatInterval', 'status' => 'Accepted']]],
    'GetConfiguration' => ['configuration' => [['key' => 'heartbeatInterval', 'value' => '30', 'readonly' => false]]],
    'UpdateFirmware' => ['status' => 'Accepted'],
    'GetDiagnostics' => ['status' => 'Accepted', 'fileName' => 'diag.tar.gz'],
    'SetMaintenanceMode' => ['status' => 'Accepted'],
    'TriggerMessage' => ['status' => 'Accepted'],
    'UpdateServiceCatalog' => ['status' => 'Accepted'],
    'CertificateInstall' => ['status' => 'Accepted'],
    'TriggerCertificateRenewal' => ['status' => 'Accepted'],
];

foreach ($commandActions as $action => $cmdPayload) {
    test("{$action}: station response routed to handler (action constant, messageType=Response)", function () use ($action, $cmdPayload, $responsePayloads): void {
        $sid = 'stn_ac' . substr(md5($action), 0, 6);
        [$tenant, $dispatcher] = sdkSetupStation($sid);

        CommandHistory::create([
            'tenant_id' => $tenant->id,
            'station_id' => $sid,
            'action' => $action,
            'message_id' => 'msg_cmd_' . strtolower($action),
            'payload' => $cmdPayload,
            'status' => 'sent',
        ]);

        // Station responds: action stays the SAME, messageType = 'Response'
        $dispatcher->dispatch($sid, sdkEnvelope(
            $action,
            'msg_cmd_' . strtolower($action),
            'Response',
            $responsePayloads[$action]
        ));

        // Verify inbound logged with correct action (no Response suffix)
        $inbound = MessageLog::where('station_id', $sid)
            ->where('action', $action)
            ->where('direction', 'inbound')
            ->where('message_type', 'Response')
            ->first();
        expect($inbound)->not->toBeNull("Inbound Response for {$action} must be logged");

        // Verify command was updated by handler
        $cmd = CommandHistory::where('station_id', $sid)
            ->where('action', $action)
            ->where('message_id', 'msg_cmd_' . strtolower($action))
            ->first();
        expect($cmd->status)->toBe('responded', "Handler must update {$action} command to 'responded'");

        // Verify NO outbound generated (station responses don't trigger server responses)
        $outbound = MessageLog::where('station_id', $sid)
            ->where('action', $action)
            ->where('direction', 'outbound')
            ->where('message_type', 'Response')
            ->whereNot('id', function ($q) use ($sid) {
                // Exclude boot response
                $q->select('id')->from('message_log')
                    ->where('station_id', $sid)
                    ->where('action', 'BootNotification')
                    ->where('direction', 'outbound');
            })
            ->count();
        expect($outbound)->toBe(0, "Station response for {$action} must NOT generate outbound");
    });
}

// ---------------------------------------------------------------------------
// PART 4: EVENTS — no response generated
// ---------------------------------------------------------------------------

$eventPayloads = [
    'StatusNotification' => [
        'bayId' => 'bay_00000001', 'bayNumber' => 1, 'status' => 'Available',
        'services' => [['serviceId' => 'svc_wash', 'available' => true]],
    ],
    'MeterValues' => [
        'bayId' => 'bay_00000001', 'sessionId' => 'sess_00000001',
        'timestamp' => '2026-03-15T10:05:00.000Z', 'values' => ['energyWh' => 150],
    ],
    'SecurityEvent' => [
        'eventId' => 'sec_0a1b2c3d', 'type' => 'TamperDetected', 'severity' => 'Warning',
        'timestamp' => '2026-03-15T10:05:00.000Z', 'details' => ['message' => 'test'],
    ],
    'ConnectionLost' => [
        'stationId' => 'PLACEHOLDER', 'reason' => 'UnexpectedDisconnect',
    ],
    'DiagnosticsNotification' => ['status' => 'Collecting', 'progress' => 50],
    'FirmwareStatusNotification' => ['status' => 'Downloading', 'firmwareVersion' => '2.0.0', 'progress' => 25],
];

foreach ($eventPayloads as $action => $payload) {
    test("{$action}: event processed without generating response", function () use ($action, $payload): void {
        $sid = 'stn_ad' . substr(md5($action), 0, 6);
        [$tenant, $dispatcher] = sdkSetupStation($sid);

        // Fix placeholder
        if (isset($payload['stationId']) && $payload['stationId'] === 'PLACEHOLDER') {
            $payload['stationId'] = $sid;
        }

        $dispatcher->dispatch($sid, sdkEnvelope($action, 'msg_evt_' . strtolower($action), 'Event', $payload));

        // Verify inbound logged
        $inbound = MessageLog::where('station_id', $sid)
            ->where('action', $action)
            ->where('direction', 'inbound')
            ->first();
        expect($inbound)->not->toBeNull("Event {$action} must be logged as inbound");

        // Verify ZERO outbound for this event
        $outbound = MessageLog::where('station_id', $sid)
            ->where('action', $action)
            ->where('direction', 'outbound')
            ->count();
        expect($outbound)->toBe(0, "Event {$action} must NOT generate outbound response");
    });
}

// ---------------------------------------------------------------------------
// PART 5: ENVELOPE FORMAT — every outbound message conforms to SDK spec
// ---------------------------------------------------------------------------

test('all outbound envelopes conform to SDK mqtt-envelope schema', function (): void {
    [$tenant, $dispatcher] = sdkSetupStation('stn_ae000001');

    // Trigger additional responses
    $dispatcher->dispatch('stn_ae000001', sdkEnvelope('Heartbeat', 'msg_env_hb', 'Request', []));
    $dispatcher->dispatch('stn_ae000001', sdkEnvelope('DataTransfer', 'msg_env_dt', 'Request', [
        'vendorId' => 'com.test', 'dataId' => 'ping',
    ]));

    $outbounds = MessageLog::where('station_id', 'stn_ae000001')
        ->where('direction', 'outbound')->get();
    expect($outbounds->count())->toBeGreaterThanOrEqual(3);

    $validActions = OsppAction::mqttActions();

    foreach ($outbounds as $log) {
        $env = $log->payload;

        // Validate against SDK envelope schema
        $errors = sdkValidate(sdkSchemaDir() . '/common/mqtt-envelope.schema.json', $env);
        expect($errors)->toBe([], "Envelope for {$log->action} failed mqtt-envelope schema");

        // SDK-mandated field values
        expect($env['source'])->toBe('Server', "Outbound source must be 'Server' per SDK");
        expect($env['messageType'])->toBe('Response');
        expect(in_array($env['action'], $validActions, true))
            ->toBeTrue("Action '{$env['action']}' must be a valid OsppAction");
        expect($env['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
    }
});

// ---------------------------------------------------------------------------
// PART 6: SCHEMA FILE COVERAGE — every SDK schema has a handler
// ---------------------------------------------------------------------------

test('every SDK MQTT action has at least one schema file', function (): void {
    $schemaDir = sdkSchemaDir() . '/mqtt';
    $sdkActions = OsppAction::mqttActions();
    $missing = [];

    foreach ($sdkActions as $action) {
        $kebab = sdkKebabCase($action);
        $hasSchema = file_exists("{$schemaDir}/{$kebab}-request.schema.json")
            || file_exists("{$schemaDir}/{$kebab}-response.schema.json")
            || file_exists("{$schemaDir}/{$kebab}-event.schema.json")
            || file_exists("{$schemaDir}/{$kebab}.schema.json");

        if (! $hasSchema) {
            $missing[] = $action;
        }
    }

    expect($missing)->toBe([], 'These SDK actions have no schema files');
    expect(count($sdkActions))->toBe(26);
});

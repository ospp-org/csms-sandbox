<?php

declare(strict_types=1);

use App\Models\CommandHistory;
use App\Models\MessageLog;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\MqttMessageDispatcher;
use App\Services\StationStateService;
use Illuminate\Support\Facades\Http;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Ospp\Protocol\SchemaPath;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function schemaDir(): string
{
    return SchemaPath::directory();
}

function validateAgainstSchema(string $schemaFile, mixed $data): array
{
    $validator = new Validator();
    $validator->setMaxErrors(10);
    $validator->resolver()->registerPrefix(
        'https://ospp-standard.org/schemas/v1/',
        schemaDir() . '/'
    );

    $schemaContent = json_decode((string) file_get_contents($schemaFile));
    $jsonData = json_decode(json_encode($data));

    $result = $validator->validate($jsonData, $schemaContent);

    if ($result->isValid()) {
        return [];
    }

    $formatter = new ErrorFormatter();

    return $formatter->format($result->error());
}

function validBootPayload(string $stationId): array
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

function createStationAndBoot(string $stationId): array
{
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => $stationId]);

    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch($stationId, makeEnvelope('BootNotification', 'msg_boot_' . $stationId, 'Request', validBootPayload($stationId)));

    $state = app(StationStateService::class);
    $state->setBayIdMapping($stationId, 'bay_00000001', 1);
    $state->setBayIdMapping($stationId, 'bay_00000002', 2);

    return [$tenant, $dispatcher];
}

function makeEnvelope(string $action, string $messageId, string $messageType, array $payload): array
{
    return [
        'action' => $action,
        'messageId' => $messageId,
        'messageType' => $messageType,
        'source' => 'Station',
        'protocolVersion' => '0.1.0',
        'timestamp' => '2026-03-14T10:00:00.000Z',
        'payload' => $payload,
    ];
}

function getLastOutboundEnvelope(string $stationId, string $action): ?array
{
    $log = MessageLog::where('station_id', $stationId)
        ->where('action', $action)
        ->where('direction', 'outbound')
        ->orderByDesc('id')
        ->first();

    return $log?->payload;
}

function assertEnvelopeConforms(array $envelope, string $expectedAction, string $expectedMessageId): void
{
    expect($envelope['source'])->toBe('Server');
    expect($envelope['messageType'])->toBe('Response');
    expect($envelope['action'])->toBe($expectedAction);
    expect($envelope['messageId'])->toBe($expectedMessageId);
    expect($envelope['protocolVersion'])->toBe('0.1.0');
    expect($envelope['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
}

// ---------------------------------------------------------------------------
// REQUEST → RESPONSE (server generates response payload)
// ---------------------------------------------------------------------------

test('BootNotification response conforms to SDK schema', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_a0000b01']);

    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch('stn_a0000b01', makeEnvelope('BootNotification', 'msg_boot01', 'Request', validBootPayload('stn_a0000b01')));

    $envelope = getLastOutboundEnvelope('stn_a0000b01', 'BootNotification');
    expect($envelope)->not->toBeNull();

    assertEnvelopeConforms($envelope, 'BootNotification', 'msg_boot01');

    $errors = validateAgainstSchema(schemaDir() . '/mqtt/boot-notification-response.schema.json', $envelope['payload']);
    expect($errors)->toBe([]);
});

test('Heartbeat response conforms to SDK schema', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_a0000c01');

    $dispatcher->dispatch('stn_a0000c01', makeEnvelope('Heartbeat', 'msg_hb01', 'Request', []));

    $envelope = getLastOutboundEnvelope('stn_a0000c01', 'Heartbeat');
    expect($envelope)->not->toBeNull();

    assertEnvelopeConforms($envelope, 'Heartbeat', 'msg_hb01');

    $errors = validateAgainstSchema(schemaDir() . '/mqtt/heartbeat-response.schema.json', $envelope['payload']);
    expect($errors)->toBe([]);
});

test('DataTransfer response conforms to SDK schema', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_a0000d01');

    $dispatcher->dispatch('stn_a0000d01', makeEnvelope('DataTransfer', 'msg_dt01', 'Request', [
        'vendorId' => 'com.example',
        'dataId' => 'diagnostics',
    ]));

    $envelope = getLastOutboundEnvelope('stn_a0000d01', 'DataTransfer');
    expect($envelope)->not->toBeNull();

    assertEnvelopeConforms($envelope, 'DataTransfer', 'msg_dt01');

    $errors = validateAgainstSchema(schemaDir() . '/mqtt/data-transfer-response.schema.json', $envelope['payload']);
    expect($errors)->toBe([]);
});

test('SignCertificate response conforms to SDK schema', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_a0000e01');

    $dispatcher->dispatch('stn_a0000e01', makeEnvelope('SignCertificate', 'msg_sc01', 'Request', [
        'certificateType' => 'StationCertificate',
        'csr' => "-----BEGIN CERTIFICATE REQUEST-----\nMIIBxTCCAWugAwIBAgI\n-----END CERTIFICATE REQUEST-----",
    ]));

    $envelope = getLastOutboundEnvelope('stn_a0000e01', 'SignCertificate');
    expect($envelope)->not->toBeNull();

    assertEnvelopeConforms($envelope, 'SignCertificate', 'msg_sc01');

    $errors = validateAgainstSchema(schemaDir() . '/mqtt/sign-certificate-response.schema.json', $envelope['payload']);
    expect($errors)->toBe([]);
});

test('AuthorizeOfflinePass response conforms to SDK schema', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_a0000f01');

    $dispatcher->dispatch('stn_a0000f01', makeEnvelope('AuthorizeOfflinePass', 'msg_aop01', 'Request', [
        'offlinePassId' => 'opass_0a1b2c3d',
        'offlinePass' => [
            'passId' => 'opass_0a1b2c3d',
            'sub' => 'sub_user001',
            'deviceId' => 'dev-phone-001',
            'issuedAt' => '2026-03-14T09:00:00.000Z',
            'expiresAt' => '2026-03-15T09:00:00.000Z',
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

    $envelope = getLastOutboundEnvelope('stn_a0000f01', 'AuthorizeOfflinePass');
    expect($envelope)->not->toBeNull();

    assertEnvelopeConforms($envelope, 'AuthorizeOfflinePass', 'msg_aop01');

    $errors = validateAgainstSchema(schemaDir() . '/mqtt/authorize-offline-pass-response.schema.json', $envelope['payload']);
    expect($errors)->toBe([]);
});

test('TransactionEvent response conforms to SDK schema', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_b0000a01');

    $dispatcher->dispatch('stn_b0000a01', makeEnvelope('TransactionEvent', 'msg_txe01', 'Request', [
        'offlineTxId' => 'otx_0a1b2c3d',
        'offlinePassId' => 'opass_0a1b2c3d',
        'userId' => 'sub_user001',
        'bayId' => 'bay_00000001',
        'serviceId' => 'svc_wash',
        'startedAt' => '2026-03-14T10:00:00.000Z',
        'endedAt' => '2026-03-14T10:30:00.000Z',
        'durationSeconds' => 1800,
        'creditsCharged' => 100,
        'receipt' => [
            'data' => 'eyJ0eCI6InRlc3QifQ==',
            'signature' => 'dGVzdHNpZ25hdHVyZQ==',
            'signatureAlgorithm' => 'ECDSA-P256-SHA256',
        ],
        'txCounter' => 1,
    ]));

    $envelope = getLastOutboundEnvelope('stn_b0000a01', 'TransactionEvent');
    expect($envelope)->not->toBeNull();

    assertEnvelopeConforms($envelope, 'TransactionEvent', 'msg_txe01');

    $errors = validateAgainstSchema(schemaDir() . '/mqtt/transaction-event-response.schema.json', $envelope['payload']);
    expect($errors)->toBe([]);
});

// ---------------------------------------------------------------------------
// EVENT handlers (no response expected)
// ---------------------------------------------------------------------------

test('StatusNotification does not generate a response', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_b0000b01');

    $dispatcher->dispatch('stn_b0000b01', makeEnvelope('StatusNotification', 'msg_sn01', 'Event', [
        'bayId' => 'bay_00000001',
        'bayNumber' => 1,
        'status' => 'Available',
        'services' => [['serviceId' => 'svc_wash', 'available' => true]],
    ]));

    $this->assertDatabaseHas('message_log', ['station_id' => 'stn_b0000b01', 'action' => 'StatusNotification', 'direction' => 'inbound']);
    expect(MessageLog::where('station_id', 'stn_b0000b01')->where('action', 'StatusNotification')->where('direction', 'outbound')->count())->toBe(0);
});

test('MeterValues does not generate a response', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_b0000c01');

    $dispatcher->dispatch('stn_b0000c01', makeEnvelope('MeterValues', 'msg_mv01', 'Event', [
        'bayId' => 'bay_00000001',
        'sessionId' => 'sess_00000001',
        'timestamp' => '2026-03-14T10:05:00.000Z',
        'values' => ['energyWh' => 150],
    ]));

    $this->assertDatabaseHas('message_log', ['station_id' => 'stn_b0000c01', 'action' => 'MeterValues', 'direction' => 'inbound']);
    expect(MessageLog::where('station_id', 'stn_b0000c01')->where('action', 'MeterValues')->where('direction', 'outbound')->count())->toBe(0);
});

test('SecurityEvent does not generate a response', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_b0000d01');

    $dispatcher->dispatch('stn_b0000d01', makeEnvelope('SecurityEvent', 'msg_se01', 'Event', [
        'eventId' => 'sec_0a1b2c3d',
        'type' => 'TamperDetected',
        'severity' => 'Warning',
        'timestamp' => '2026-03-14T10:05:00.000Z',
        'details' => ['message' => 'Cabinet opened'],
    ]));

    $this->assertDatabaseHas('message_log', ['station_id' => 'stn_b0000d01', 'action' => 'SecurityEvent', 'direction' => 'inbound']);
    expect(MessageLog::where('station_id', 'stn_b0000d01')->where('action', 'SecurityEvent')->where('direction', 'outbound')->count())->toBe(0);
});

test('ConnectionLost does not generate a response', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_b0000e01');

    $dispatcher->dispatch('stn_b0000e01', makeEnvelope('ConnectionLost', 'msg_cl01', 'Event', [
        'stationId' => 'stn_b0000e01',
        'reason' => 'UnexpectedDisconnect',
    ]));

    $this->assertDatabaseHas('message_log', ['station_id' => 'stn_b0000e01', 'action' => 'ConnectionLost', 'direction' => 'inbound']);
    expect(MessageLog::where('station_id', 'stn_b0000e01')->where('action', 'ConnectionLost')->where('direction', 'outbound')->count())->toBe(0);
});

test('DiagnosticsNotification does not generate a response', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_b0000f01');

    $dispatcher->dispatch('stn_b0000f01', makeEnvelope('DiagnosticsNotification', 'msg_dn01', 'Event', [
        'status' => 'Collecting',
        'progress' => 50,
    ]));

    $this->assertDatabaseHas('message_log', ['station_id' => 'stn_b0000f01', 'action' => 'DiagnosticsNotification', 'direction' => 'inbound']);
    expect(MessageLog::where('station_id', 'stn_b0000f01')->where('action', 'DiagnosticsNotification')->where('direction', 'outbound')->count())->toBe(0);
});

test('FirmwareStatusNotification does not generate a response', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_c0000a01');

    $dispatcher->dispatch('stn_c0000a01', makeEnvelope('FirmwareStatusNotification', 'msg_fsn01', 'Event', [
        'status' => 'Downloading',
        'firmwareVersion' => '2.0.0',
        'progress' => 25,
    ]));

    $this->assertDatabaseHas('message_log', ['station_id' => 'stn_c0000a01', 'action' => 'FirmwareStatusNotification', 'direction' => 'inbound']);
    expect(MessageLog::where('station_id', 'stn_c0000a01')->where('action', 'FirmwareStatusNotification')->where('direction', 'outbound')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// RESPONSE handlers (station responds to server command — no outbound)
// ---------------------------------------------------------------------------

test('StartServiceResponse does not generate outbound', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_c0000b01');

    CommandHistory::create([
        'tenant_id' => $tenant->id, 'station_id' => 'stn_c0000b01', 'action' => 'StartService',
        'message_id' => 'msg_ssr01', 'payload' => ['bayId' => 'bay_00000001', 'serviceId' => 'svc_wash', 'sessionId' => 'sess_00000001'], 'status' => 'sent',
    ]);

    $dispatcher->dispatch('stn_c0000b01', makeEnvelope('StartServiceResponse', 'msg_ssr01', 'Response', ['status' => 'Accepted']));

    $this->assertDatabaseHas('message_log', ['station_id' => 'stn_c0000b01', 'action' => 'StartServiceResponse', 'direction' => 'inbound']);
    expect(MessageLog::where('station_id', 'stn_c0000b01')->where('action', 'StartServiceResponse')->where('direction', 'outbound')->count())->toBe(0);
});

test('ResetResponse does not generate outbound', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_c0000c01');

    CommandHistory::create([
        'tenant_id' => $tenant->id, 'station_id' => 'stn_c0000c01', 'action' => 'Reset',
        'message_id' => 'msg_rr01', 'payload' => ['type' => 'Soft'], 'status' => 'sent',
    ]);

    $dispatcher->dispatch('stn_c0000c01', makeEnvelope('ResetResponse', 'msg_rr01', 'Response', ['status' => 'Accepted']));
    expect(MessageLog::where('station_id', 'stn_c0000c01')->where('action', 'ResetResponse')->where('direction', 'outbound')->count())->toBe(0);
});

test('ChangeConfigurationResponse does not generate outbound', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_c0000d01');

    CommandHistory::create([
        'tenant_id' => $tenant->id, 'station_id' => 'stn_c0000d01', 'action' => 'ChangeConfiguration',
        'message_id' => 'msg_ccr01', 'payload' => ['keys' => [['key' => 'heartbeatInterval', 'value' => '60']]], 'status' => 'sent',
    ]);

    $dispatcher->dispatch('stn_c0000d01', makeEnvelope('ChangeConfigurationResponse', 'msg_ccr01', 'Response', [
        'results' => [['key' => 'heartbeatInterval', 'status' => 'Accepted']],
    ]));
    expect(MessageLog::where('station_id', 'stn_c0000d01')->where('action', 'ChangeConfigurationResponse')->where('direction', 'outbound')->count())->toBe(0);
});

test('GetConfigurationResponse does not generate outbound', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_c0000e01');

    CommandHistory::create([
        'tenant_id' => $tenant->id, 'station_id' => 'stn_c0000e01', 'action' => 'GetConfiguration',
        'message_id' => 'msg_gcr01', 'payload' => [], 'status' => 'sent',
    ]);

    $dispatcher->dispatch('stn_c0000e01', makeEnvelope('GetConfigurationResponse', 'msg_gcr01', 'Response', [
        'configuration' => [['key' => 'heartbeatInterval', 'value' => '30', 'readonly' => false]],
    ]));
    expect(MessageLog::where('station_id', 'stn_c0000e01')->where('action', 'GetConfigurationResponse')->where('direction', 'outbound')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// ENVELOPE VALIDATION
// ---------------------------------------------------------------------------

test('All outbound envelopes conform to mqtt-envelope schema', function (): void {
    [$tenant, $dispatcher] = createStationAndBoot('stn_d0000a01');

    $dispatcher->dispatch('stn_d0000a01', makeEnvelope('Heartbeat', 'msg_env_hb', 'Request', []));
    $dispatcher->dispatch('stn_d0000a01', makeEnvelope('DataTransfer', 'msg_env_dt', 'Request', [
        'vendorId' => 'com.test', 'dataId' => 'ping',
    ]));

    $outbound = MessageLog::where('station_id', 'stn_d0000a01')->where('direction', 'outbound')->get();
    expect($outbound->count())->toBeGreaterThanOrEqual(2);

    foreach ($outbound as $log) {
        $errors = validateAgainstSchema(schemaDir() . '/common/mqtt-envelope.schema.json', $log->payload);
        expect($errors)->toBe([], "Envelope for {$log->action} must conform to mqtt-envelope.schema.json");
    }
});

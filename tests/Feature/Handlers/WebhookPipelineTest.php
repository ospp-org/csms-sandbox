<?php

declare(strict_types=1);

use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;
use Illuminate\Support\Facades\Http;

/**
 * Helper to boot a station through the dispatcher (prerequisite for most handlers).
 */
function bootStation(string $stationId): void
{
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    app(\App\Services\MqttMessageDispatcher::class)->dispatch($stationId, [
        'action' => 'BootNotification',
        'messageId' => 'msg_boot_' . $stationId,
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.1.0',
        'timestamp' => '2026-03-09T10:00:00.000Z',
        'payload' => [
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
            'networkInfo' => [
                'connectionType' => 'Ethernet',
            ],
        ],
    ]);
}

test('StatusNotification through webhook pipeline logs message and updates bay', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_a0000001']);

    bootStation('stn_a0000001');

    $this->postJson('/internal/mqtt/webhook', [
        'topic' => 'ospp/v1/stations/stn_a0000001/to-server',
        'payload' => json_encode([
            'action' => 'StatusNotification',
            'messageId' => 'msg_sn_001',
            'messageType' => 'Event',
            'source' => 'Station',
            'protocolVersion' => '0.1.0',
            'timestamp' => '2026-03-09T10:01:00.000Z',
            'payload' => [
                'bayId' => 'bay_00000001',
                'bayNumber' => 1,
                'status' => 'Available',
                'services' => [
                    ['serviceId' => 'svc_wash', 'available' => true],
                ],
            ],
        ]),
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')])->assertStatus(200);

    $this->assertDatabaseHas('message_log', [
        'station_id' => 'stn_a0000001',
        'action' => 'StatusNotification',
        'direction' => 'inbound',
    ]);

    $state = app(StationStateService::class);
    expect($state->getBayStatus('stn_a0000001', 1))->toBe('Available');
});

test('DataTransfer through webhook pipeline returns Accepted', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_a0000002']);

    bootStation('stn_a0000002');

    $this->postJson('/internal/mqtt/webhook', [
        'topic' => 'ospp/v1/stations/stn_a0000002/to-server',
        'payload' => json_encode([
            'action' => 'DataTransfer',
            'messageId' => 'msg_dt_001',
            'messageType' => 'Request',
            'source' => 'Station',
            'protocolVersion' => '0.1.0',
            'timestamp' => '2026-03-09T10:02:00.000Z',
            'payload' => [
                'vendorId' => 'com.example',
                'dataId' => 'diagnostics',
            ],
        ]),
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')])->assertStatus(200);

    $this->assertDatabaseHas('message_log', [
        'station_id' => 'stn_a0000002',
        'action' => 'DataTransfer',
        'direction' => 'inbound',
    ]);

    // DataTransfer sends a response (Accepted), so outbound should also be logged
    $this->assertDatabaseHas('message_log', [
        'station_id' => 'stn_a0000002',
        'action' => 'DataTransfer',
        'direction' => 'outbound',
    ]);
});

test('StartServiceResponse through webhook pipeline updates bay status', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_a0000003']);

    bootStation('stn_a0000003');

    // Create pending command that the response will match
    CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_a0000003',
        'action' => 'StartService',
        'message_id' => 'msg_ss_001',
        'payload' => ['bayId' => 'bay_00000001', 'serviceId' => 'svc_wash', 'sessionId' => 'sess_00000001'],
        'status' => 'sent',
    ]);

    app(StationStateService::class)->setBayIdMapping('stn_a0000003', 'bay_00000001', 1);

    $this->postJson('/internal/mqtt/webhook', [
        'topic' => 'ospp/v1/stations/stn_a0000003/to-server',
        'payload' => json_encode([
            'action' => 'StartServiceResponse',
            'messageId' => 'msg_ss_001',
            'messageType' => 'Response',
            'source' => 'Station',
            'protocolVersion' => '0.1.0',
            'timestamp' => '2026-03-09T10:03:00.000Z',
            'payload' => ['status' => 'Accepted'],
        ]),
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')])->assertStatus(200);

    $this->assertDatabaseHas('message_log', [
        'station_id' => 'stn_a0000003',
        'action' => 'StartServiceResponse',
        'direction' => 'inbound',
    ]);

    $state = app(StationStateService::class);
    expect($state->getBayStatus('stn_a0000003', 1))->toBe('Occupied');

    $this->assertDatabaseHas('command_history', [
        'message_id' => 'msg_ss_001',
        'status' => 'responded',
    ]);
});

test('ResetResponse through webhook pipeline updates lifecycle', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_a0000004']);

    bootStation('stn_a0000004');

    CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_a0000004',
        'action' => 'Reset',
        'message_id' => 'msg_rs_001',
        'payload' => ['type' => 'Soft'],
        'status' => 'sent',
    ]);

    $this->postJson('/internal/mqtt/webhook', [
        'topic' => 'ospp/v1/stations/stn_a0000004/to-server',
        'payload' => json_encode([
            'action' => 'ResetResponse',
            'messageId' => 'msg_rs_001',
            'messageType' => 'Response',
            'source' => 'Station',
            'protocolVersion' => '0.1.0',
            'timestamp' => '2026-03-09T10:04:00.000Z',
            'payload' => ['status' => 'Accepted'],
        ]),
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')])->assertStatus(200);

    $this->assertDatabaseHas('message_log', [
        'station_id' => 'stn_a0000004',
        'action' => 'ResetResponse',
        'direction' => 'inbound',
    ]);

    $state = app(StationStateService::class);
    expect($state->getLifecycle('stn_a0000004'))->toBe('resetting');
});

test('SecurityEvent through webhook pipeline logs event', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_a0000005']);

    bootStation('stn_a0000005');

    $this->postJson('/internal/mqtt/webhook', [
        'topic' => 'ospp/v1/stations/stn_a0000005/to-server',
        'payload' => json_encode([
            'action' => 'SecurityEvent',
            'messageId' => 'msg_se_001',
            'messageType' => 'Event',
            'source' => 'Station',
            'protocolVersion' => '0.1.0',
            'timestamp' => '2026-03-09T10:05:00.000Z',
            'payload' => [
                'eventId' => 'sec_00000001',
                'type' => 'TamperDetected',
                'severity' => 'Warning',
                'timestamp' => '2026-03-09T10:05:00.000Z',
                'details' => ['message' => 'Cabinet opened'],
            ],
        ]),
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')])->assertStatus(200);

    $this->assertDatabaseHas('message_log', [
        'station_id' => 'stn_a0000005',
        'action' => 'SecurityEvent',
        'direction' => 'inbound',
    ]);
});

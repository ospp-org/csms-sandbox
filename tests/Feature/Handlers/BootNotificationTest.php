<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\MqttMessageDispatcher;
use App\Services\StationStateService;
use Illuminate\Support\Facades\Http;

test('BootNotification from registered station returns Accepted and updates state', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_test0001',
    ]);

    $envelope = [
        'action' => 'BootNotification',
        'messageId' => 'msg_boot_001',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.2.1',
        'timestamp' => '2026-03-09T10:00:05.000Z',
        'payload' => [
            'stationId' => 'stn_ae000001',
            'stationModel' => 'WashPro 5000',
            'stationVendor' => 'CSMS Dev',
            'firmwareVersion' => '1.0.0',
            'serialNumber' => 'SN000001',
            'bayCount' => 4,
            'uptimeSeconds' => 120,
            'pendingOfflineTransactions' => 0,
            'timezone' => 'Europe/Bucharest',
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
    ];

    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch('stn_test0001', $envelope);

    // Station DB updated
    $station->refresh();
    expect($station->is_connected)->toBeTrue();
    expect($station->firmware_version)->toBe('1.0.0');
    expect($station->station_model)->toBe('WashPro 5000');
    expect($station->station_vendor)->toBe('CSMS Dev');
    expect($station->bay_count)->toBe(4);

    // Redis state updated
    $stateService = app(StationStateService::class);
    expect($stateService->getLifecycle('stn_test0001'))->toBe('online');
    expect($stateService->isConnected('stn_test0001'))->toBeTrue();
    expect($stateService->getBayStatus('stn_test0001', 1))->toBe('Unknown');

    // Message logged
    $this->assertDatabaseHas('message_log', [
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_test0001',
        'action' => 'BootNotification',
        'direction' => 'inbound',
    ]);
});

test('BootNotification initializes all bays in Redis', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_baytest']);

    $envelope = [
        'action' => 'BootNotification',
        'messageId' => 'msg_002',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.2.1',
        'timestamp' => '2026-03-09T10:00:05.000Z',
        'payload' => [
            'stationId' => 'stn_be000002',
            'stationModel' => 'T',
            'stationVendor' => 'T',
            'firmwareVersion' => '1.0.0',
            'serialNumber' => 'SN000002',
            'bayCount' => 3,
            'uptimeSeconds' => 0,
            'pendingOfflineTransactions' => 0,
            'timezone' => 'UTC',
            'bootReason' => 'PowerOn',
            'capabilities' => ['bleSupported' => false, 'offlineModeSupported' => false, 'meterValuesSupported' => true],
            'networkInfo' => ['connectionType' => 'Ethernet'],
        ],
    ];

    app(MqttMessageDispatcher::class)->dispatch('stn_baytest', $envelope);

    $state = app(StationStateService::class);
    $bays = $state->getAllBays('stn_baytest');

    expect($bays)->toHaveCount(3);
    expect($bays[1]['status'])->toBe('Unknown');
    expect($bays[2]['status'])->toBe('Unknown');
    expect($bays[3]['status'])->toBe('Unknown');
});

test('BootNotification from unknown station is ignored', function (): void {
    Http::fake();

    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch('stn_nonexistent', [
        'action' => 'BootNotification',
        'messageId' => 'msg_003',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.2.1',
        'timestamp' => '2026-03-09T10:00:05.000Z',
        'payload' => [
            'stationId' => 'stn_ce000003',
            'stationModel' => 'T',
            'stationVendor' => 'T',
            'firmwareVersion' => '1.0.0',
            'serialNumber' => 'SN000003',
            'bayCount' => 1,
            'uptimeSeconds' => 0,
            'pendingOfflineTransactions' => 0,
            'timezone' => 'UTC',
            'bootReason' => 'PowerOn',
            'capabilities' => ['bleSupported' => false, 'offlineModeSupported' => false, 'meterValuesSupported' => true],
            'networkInfo' => ['connectionType' => 'Ethernet'],
        ],
    ]);

    $this->assertDatabaseCount('message_log', 0);
    Http::assertNothingSent();
});

test('BootNotification updates tenant_stations.protocol_version from envelope', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_ce000004',
        'protocol_version' => '0.1.0',
    ]);

    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch('stn_ce000004', [
        'action' => 'BootNotification',
        'messageId' => 'msg_pv_001',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.2.1',
        'timestamp' => '2026-03-20T10:00:00.000Z',
        'payload' => [
            'stationId' => 'stn_ce000004',
            'firmwareVersion' => '1.0.0',
            'stationModel' => 'TestModel',
            'stationVendor' => 'TestVendor',
            'serialNumber' => 'SN000004',
            'bayCount' => 2,
            'uptimeSeconds' => 0,
            'pendingOfflineTransactions' => 0,
            'timezone' => 'UTC',
            'bootReason' => 'PowerOn',
            'capabilities' => ['bleSupported' => false, 'offlineModeSupported' => false, 'meterValuesSupported' => true],
            'networkInfo' => ['connectionType' => 'Ethernet'],
        ],
    ]);

    $station->refresh();
    expect($station->protocol_version)->toBe('0.2.1');
});

test('BootNotification with wrong protocolVersion returns Rejected 1007', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_ce000005',
        'protocol_version' => '0.2.1',
    ]);

    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch('stn_ce000005', [
        'action' => 'BootNotification',
        'messageId' => 'msg_pv_002',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '9.9.9',
        'timestamp' => '2026-03-20T10:00:00.000Z',
        'payload' => [
            'stationId' => 'stn_ce000005',
            'firmwareVersion' => '1.0.0',
            'stationModel' => 'TestModel',
            'stationVendor' => 'TestVendor',
            'serialNumber' => 'SN000005',
            'bayCount' => 2,
            'uptimeSeconds' => 0,
            'pendingOfflineTransactions' => 0,
            'timezone' => 'UTC',
            'bootReason' => 'PowerOn',
            'capabilities' => ['bleSupported' => false, 'offlineModeSupported' => false, 'meterValuesSupported' => true],
            'networkInfo' => ['connectionType' => 'Ethernet'],
        ],
    ]);

    $outbound = \App\Models\MessageLog::where('station_id', 'stn_ce000005')
        ->where('action', 'BootNotification')
        ->where('direction', 'outbound')
        ->first();

    expect($outbound)->not->toBeNull();
    expect($outbound->payload['payload']['status'])->toBe('Rejected');
    expect($outbound->payload['payload']['supportedVersions'])->toContain('0.2.1');

    // protocol_version NOT updated
    $station->refresh();
    expect($station->protocol_version)->toBe('0.2.1');
});

test('BootNotification with empty protocolVersion returns Rejected', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_ce000006',
        'protocol_version' => '0.2.1',
    ]);

    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch('stn_ce000006', [
        'action' => 'BootNotification',
        'messageId' => 'msg_pv_003',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '',
        'timestamp' => '2026-03-20T10:00:00.000Z',
        'payload' => [
            'stationId' => 'stn_ce000006',
            'firmwareVersion' => '1.0.0',
            'stationModel' => 'TestModel',
            'stationVendor' => 'TestVendor',
            'serialNumber' => 'SN000006',
            'bayCount' => 2,
            'uptimeSeconds' => 0,
            'pendingOfflineTransactions' => 0,
            'timezone' => 'UTC',
            'bootReason' => 'PowerOn',
            'capabilities' => ['bleSupported' => false, 'offlineModeSupported' => false, 'meterValuesSupported' => true],
            'networkInfo' => ['connectionType' => 'Ethernet'],
        ],
    ]);

    $outbound = \App\Models\MessageLog::where('station_id', 'stn_ce000006')
        ->where('action', 'BootNotification')
        ->where('direction', 'outbound')
        ->first();

    expect($outbound)->not->toBeNull();
    expect($outbound->payload['payload']['status'])->toBe('Rejected');

    // protocol_version NOT updated
    $station->refresh();
    expect($station->protocol_version)->toBe('0.2.1');
});

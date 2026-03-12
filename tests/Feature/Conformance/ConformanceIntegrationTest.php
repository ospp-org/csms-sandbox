<?php

declare(strict_types=1);

use App\Models\ConformanceResult;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\MqttMessageDispatcher;
use App\Services\StationStateService;
use Illuminate\Support\Facades\Http;

test('BootNotification dispatched through pipeline records conformance result', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_ci000001']);

    $envelope = [
        'action' => 'BootNotification',
        'messageId' => 'msg_ci_001',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.1.0',
        'timestamp' => '2026-03-09T10:00:05.000Z',
        'payload' => [
            'stationId' => 'stn_a0000001',
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

    app(MqttMessageDispatcher::class)->dispatch('stn_ci000001', $envelope);

    $this->assertDatabaseHas('conformance_results', [
        'tenant_id' => $tenant->id,
        'action' => 'BootNotification',
        'status' => 'passed',
    ]);
});

test('invalid schema records failed conformance result', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create(['validation_mode' => 'lenient']);
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_ci000002']);

    $envelope = [
        'action' => 'BootNotification',
        'messageId' => 'msg_ci_002',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.1.0',
        'timestamp' => '2026-03-09T10:00:05.000Z',
        'payload' => [
            'stationId' => 'stn_a0000002',
            // Missing required fields — schema should fail
        ],
    ];

    app(MqttMessageDispatcher::class)->dispatch('stn_ci000002', $envelope);

    $this->assertDatabaseHas('conformance_results', [
        'tenant_id' => $tenant->id,
        'action' => 'BootNotification',
        'status' => 'failed',
    ]);
});

test('conformance report reflects pipeline results', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_ci000003']);

    $stateService = app(StationStateService::class);
    $stateService->resetState('stn_ci000003', 2);

    // Send valid BootNotification
    app(MqttMessageDispatcher::class)->dispatch('stn_ci000003', [
        'action' => 'BootNotification',
        'messageId' => 'msg_ci_003',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.1.0',
        'timestamp' => '2026-03-09T10:00:05.000Z',
        'payload' => [
            'stationId' => 'stn_a0000003',
            'stationModel' => 'T',
            'stationVendor' => 'T',
            'firmwareVersion' => '1.0.0',
            'serialNumber' => 'SN000003',
            'bayCount' => 2,
            'uptimeSeconds' => 0,
            'pendingOfflineTransactions' => 0,
            'timezone' => 'UTC',
            'bootReason' => 'PowerOn',
            'capabilities' => ['bleSupported' => false, 'offlineModeSupported' => false, 'meterValuesSupported' => true],
            'networkInfo' => ['connectionType' => 'Ethernet'],
        ],
    ]);

    // Check API
    $response = $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/conformance');

    $response->assertStatus(200);

    $results = collect($response->json('results'));
    $boot = $results->firstWhere('action', 'BootNotification');

    expect($boot['status'])->toBe('passed');
});

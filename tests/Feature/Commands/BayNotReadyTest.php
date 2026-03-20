<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\CommandService;
use App\Services\MqttMessageDispatcher;
use App\Services\StationStateService;
use Illuminate\Support\Facades\Http;

function bootForBayTest(string $stationId): array
{
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => $stationId,
        'is_connected' => true,
    ]);

    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch($stationId, [
        'action' => 'BootNotification',
        'messageId' => 'msg_boot_' . $stationId,
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.1.0',
        'timestamp' => '2026-03-20T10:00:00.000Z',
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
            'capabilities' => ['bleSupported' => false, 'offlineModeSupported' => false, 'meterValuesSupported' => true],
            'networkInfo' => ['connectionType' => 'Ethernet'],
        ],
    ]);

    // Set bay mapping so resolveBayNumber works
    $state = app(StationStateService::class);
    $state->setBayIdMapping($stationId, 'bay_00000001', 1);

    return [$tenant, $dispatcher];
}

test('StartService rejected with BAY_NOT_READY when bay is Unknown', function (): void {
    [$tenant, $dispatcher] = bootForBayTest('stn_ba000001');

    // Bays are Unknown after boot (no StatusNotification sent)
    $state = app(StationStateService::class);
    expect($state->getBayStatus('stn_ba000001', 1))->toBe('Unknown');

    $commandService = app(CommandService::class);
    $result = $commandService->send(
        tenantId: $tenant->id,
        action: 'StartService',
        parameters: [
            'sessionId' => 'sess_00000001',
            'bayId' => 'bay_00000001',
            'serviceId' => 'svc_wash',
            'durationSeconds' => 300,
            'sessionSource' => 'MobileApp',
        ],
    );

    expect($result->success)->toBeFalse();
    expect($result->errorCode)->toBe('BAY_NOT_READY');
});

test('ReserveBay rejected with BAY_NOT_READY when bay is Unknown', function (): void {
    [$tenant, $dispatcher] = bootForBayTest('stn_ba000002');

    $state = app(StationStateService::class);
    expect($state->getBayStatus('stn_ba000002', 1))->toBe('Unknown');

    $commandService = app(CommandService::class);
    $result = $commandService->send(
        tenantId: $tenant->id,
        action: 'ReserveBay',
        parameters: [
            'bayId' => 'bay_00000001',
            'reservationId' => 'rsv_00000001',
            'expirationTime' => '2026-03-20T10:05:00.000Z',
            'sessionSource' => 'WebPayment',
        ],
    );

    expect($result->success)->toBeFalse();
    expect($result->errorCode)->toBe('BAY_NOT_READY');
});

test('StartService accepted after StatusNotification resolves bay to Available', function (): void {
    [$tenant, $dispatcher] = bootForBayTest('stn_ba000003');

    // Send StatusNotification to resolve bay from Unknown -> Available
    $dispatcher->dispatch('stn_ba000003', [
        'action' => 'StatusNotification',
        'messageId' => 'msg_sn_001',
        'messageType' => 'Event',
        'source' => 'Station',
        'protocolVersion' => '0.1.0',
        'timestamp' => '2026-03-20T10:00:01.000Z',
        'payload' => [
            'bayId' => 'bay_00000001',
            'bayNumber' => 1,
            'status' => 'Available',
            'services' => [['serviceId' => 'svc_wash', 'available' => true]],
        ],
    ]);

    $state = app(StationStateService::class);
    expect($state->getBayStatus('stn_ba000003', 1))->toBe('Available');

    $commandService = app(CommandService::class);
    $result = $commandService->send(
        tenantId: $tenant->id,
        action: 'StartService',
        parameters: [
            'sessionId' => 'sess_00000001',
            'bayId' => 'bay_00000001',
            'serviceId' => 'svc_wash',
            'durationSeconds' => 300,
            'sessionSource' => 'MobileApp',
        ],
    );

    // Should succeed (sent to station, not BAY_NOT_READY)
    expect($result->success)->toBeTrue();
});

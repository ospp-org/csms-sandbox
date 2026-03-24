<?php

declare(strict_types=1);

use App\Models\MessageLog;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\MqttMessageDispatcher;
use App\Services\StationStateService;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// GET /api/v1/stations/{stationId}
// ---------------------------------------------------------------------------

test('showById returns station state for own station', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_sc000001',
        'bay_count' => 2,
        'is_connected' => true,
    ]);

    $state = app(StationStateService::class);
    $state->resetState('stn_sc000001', 2);
    $state->setBayStatus('stn_sc000001', 1, 'Available');

    $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/stations/stn_sc000001')
        ->assertOk()
        ->assertJsonPath('station_id', 'stn_sc000001')
        ->assertJsonPath('bay_count', 2)
        ->assertJsonPath('is_connected', true)
        ->assertJsonPath('lifecycle', 'online')
        ->assertJsonStructure(['station_id', 'protocol_version', 'bay_count', 'is_connected', 'lifecycle', 'bays']);
});

test('showById returns 404 for other tenant station', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    TenantStation::factory()->for($tenantB)->create(['station_id' => 'stn_sc000002']);

    $this->actingAs($tenantA, 'jwt')
        ->getJson('/api/v1/stations/stn_sc000002')
        ->assertNotFound();
});

test('showById returns 404 for nonexistent station', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/stations/stn_doesnotexist')
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// POST /api/v1/stations/{stationId}/force-reject
// ---------------------------------------------------------------------------

test('forceReject sets flag on station', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_sc000003',
        'force_boot_reject' => false,
    ]);

    $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/stations/stn_sc000003/force-reject')
        ->assertOk()
        ->assertJsonPath('station_id', 'stn_sc000003');

    $station->refresh();
    expect($station->force_boot_reject)->toBeTrue();
});

test('forceReject causes next BootNotification to be Rejected', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_a0000004',
        'force_boot_reject' => true,
    ]);

    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch('stn_a0000004', [
        'action' => 'BootNotification',
        'messageId' => 'msg_fr_001',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.2.1',
        'timestamp' => '2026-03-24T10:00:00.000Z',
        'payload' => [
            'stationId' => 'stn_a0000004',
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

    $outbound = MessageLog::where('station_id', 'stn_a0000004')
        ->where('action', 'BootNotification')
        ->where('direction', 'outbound')
        ->first();

    expect($outbound)->not->toBeNull();
    expect($outbound->payload['payload']['status'])->toBe('Rejected');

    // Flag auto-cleared after use
    $station = TenantStation::where('station_id', 'stn_a0000004')->first();
    expect($station->force_boot_reject)->toBeFalse();
});

// ---------------------------------------------------------------------------
// DELETE /api/v1/stations/{stationId}/force-reject
// ---------------------------------------------------------------------------

test('clearForceReject clears the flag', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_sc000005',
        'force_boot_reject' => true,
    ]);

    $this->actingAs($tenant, 'jwt')
        ->deleteJson('/api/v1/stations/stn_sc000005/force-reject')
        ->assertOk();

    $station->refresh();
    expect($station->force_boot_reject)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Auth required
// ---------------------------------------------------------------------------

test('simulator control endpoints require authentication', function (): void {
    $this->getJson('/api/v1/stations/stn_00000001')->assertStatus(401);
    $this->postJson('/api/v1/stations/stn_00000001/force-reject')->assertStatus(401);
    $this->deleteJson('/api/v1/stations/stn_00000001/force-reject')->assertStatus(401);
});

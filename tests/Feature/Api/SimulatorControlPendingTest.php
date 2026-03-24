<?php

declare(strict_types=1);

use App\Models\MessageLog;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\MqttMessageDispatcher;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// POST /api/v1/stations/{stationId}/force-pending
// ---------------------------------------------------------------------------

test('forcePending sets flag with default retry interval', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_fp000001']);

    $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/stations/stn_fp000001/force-pending')
        ->assertOk()
        ->assertJsonPath('station_id', 'stn_fp000001');

    $station->refresh();
    expect($station->force_boot_pending)->toBeTrue();
    expect($station->boot_retry_interval)->toBe(30);
});

test('forcePending accepts custom retry interval', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_fp000002']);

    $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/stations/stn_fp000002/force-pending', ['retry_interval' => 15])
        ->assertOk();

    $station->refresh();
    expect($station->boot_retry_interval)->toBe(15);
});

test('forcePending causes next BootNotification to return Pending', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_a0000010',
        'force_boot_pending' => true,
        'boot_retry_interval' => 10,
    ]);

    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch('stn_a0000010', [
        'action' => 'BootNotification',
        'messageId' => 'msg_fp_001',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.2.1',
        'timestamp' => '2026-03-24T10:00:00.000Z',
        'payload' => [
            'stationId' => 'stn_a0000010',
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

    $outbound = MessageLog::where('station_id', 'stn_a0000010')
        ->where('action', 'BootNotification')
        ->where('direction', 'outbound')
        ->first();

    expect($outbound)->not->toBeNull();
    expect($outbound->payload['payload']['status'])->toBe('Pending');
    expect($outbound->payload['payload']['retryInterval'])->toBe(10);

    // Flag auto-cleared
    $station = TenantStation::where('station_id', 'stn_a0000010')->first();
    expect($station->force_boot_pending)->toBeFalse();
    expect($station->boot_retry_interval)->toBeNull();
});

// ---------------------------------------------------------------------------
// DELETE /api/v1/stations/{stationId}/force-pending
// ---------------------------------------------------------------------------

test('clearForcePending clears flag', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_fp000005',
        'force_boot_pending' => true,
        'boot_retry_interval' => 20,
    ]);

    $this->actingAs($tenant, 'jwt')
        ->deleteJson('/api/v1/stations/stn_fp000005/force-pending')
        ->assertOk();

    $station->refresh();
    expect($station->force_boot_pending)->toBeFalse();
    expect($station->boot_retry_interval)->toBeNull();
});

// ---------------------------------------------------------------------------
// POST /api/v1/stations/{stationId}/trigger-data-transfer
// ---------------------------------------------------------------------------

test('triggerDataTransfer sends DataTransfer command', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_fp000006',
        'is_connected' => true,
    ]);

    $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/stations/stn_fp000006/trigger-data-transfer', [
            'vendor_id' => 'com.test',
            'data_id' => 'ping',
        ])
        ->assertOk()
        ->assertJsonPath('station_id', 'stn_fp000006')
        ->assertJsonStructure(['message', 'station_id', 'message_id']);
});

test('triggerDataTransfer returns 409 when station not connected', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_fp000007',
        'is_connected' => false,
    ]);

    $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/stations/stn_fp000007/trigger-data-transfer')
        ->assertStatus(409);
});

test('triggerDataTransfer returns 404 for other tenant station', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    TenantStation::factory()->for($tenantB)->create(['station_id' => 'stn_fp000008']);

    $this->actingAs($tenantA, 'jwt')
        ->postJson('/api/v1/stations/stn_fp000008/trigger-data-transfer')
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Auth required
// ---------------------------------------------------------------------------

test('new simulator control endpoints require authentication', function (): void {
    $this->postJson('/api/v1/stations/stn_00000001/force-pending')->assertStatus(401);
    $this->deleteJson('/api/v1/stations/stn_00000001/force-pending')->assertStatus(401);
    $this->postJson('/api/v1/stations/stn_00000001/trigger-data-transfer')->assertStatus(401);
});

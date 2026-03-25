<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;
use Illuminate\Support\Facades\Http;

test('trigger-command sends valid action to connected station', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_tc000001',
        'is_connected' => true,
    ]);

    $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/stations/stn_tc000001/trigger-command', [
            'action' => 'Reset',
            'payload' => ['type' => 'Soft'],
        ])
        ->assertOk()
        ->assertJsonPath('station_id', 'stn_tc000001')
        ->assertJsonPath('action', 'Reset')
        ->assertJsonStructure(['message', 'station_id', 'action', 'message_id']);
});

test('trigger-command returns 400 for missing action', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_tc000002',
        'is_connected' => true,
    ]);

    $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/stations/stn_tc000002/trigger-command', [
            'payload' => ['type' => 'Soft'],
        ])
        ->assertStatus(400)
        ->assertJsonPath('error', 'MISSING_ACTION');
});

test('trigger-command returns 400 for invalid action', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_tc000003',
        'is_connected' => true,
    ]);

    $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/stations/stn_tc000003/trigger-command', [
            'action' => 'FakeAction',
            'payload' => [],
        ])
        ->assertStatus(400)
        ->assertJsonPath('error', 'INVALID_ACTION');
});

test('trigger-command returns 409 when station not connected', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_tc000004',
        'is_connected' => false,
    ]);

    $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/stations/stn_tc000004/trigger-command', [
            'action' => 'Reset',
            'payload' => ['type' => 'Soft'],
        ])
        ->assertStatus(409)
        ->assertJsonPath('error', 'STATION_NOT_CONNECTED');
});

test('trigger-command returns 404 for other tenant station', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    TenantStation::factory()->for($tenantB)->create(['station_id' => 'stn_tc000005']);

    $this->actingAs($tenantA, 'jwt')
        ->postJson('/api/v1/stations/stn_tc000005/trigger-command', [
            'action' => 'Reset',
            'payload' => ['type' => 'Soft'],
        ])
        ->assertStatus(404);
});

test('trigger-command requires authentication', function (): void {
    $this->postJson('/api/v1/stations/stn_00000001/trigger-command', [
        'action' => 'Reset',
        'payload' => [],
    ])->assertStatus(401);
});

test('trigger-data-transfer delegates to trigger-command', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_tc000006',
        'is_connected' => true,
    ]);

    $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/stations/stn_tc000006/trigger-data-transfer', [
            'vendor_id' => 'com.test',
            'data_id' => 'ping',
        ])
        ->assertOk()
        ->assertJsonPath('action', 'DataTransfer');
});

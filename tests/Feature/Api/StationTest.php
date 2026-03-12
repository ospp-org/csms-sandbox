<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('GET /api/v1/station returns station info', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_00000042',
        'mqtt_username' => 'sandbox_testuser',
        'firmware_version' => '2.0.0',
        'bay_count' => 4,
    ]);

    $response = $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/station');

    $response->assertStatus(200)
        ->assertJsonPath('station_id', 'stn_00000042')
        ->assertJsonPath('mqtt.username', 'sandbox_testuser')
        ->assertJsonPath('mqtt.password_available', true)
        ->assertJsonPath('status.firmware_version', '2.0.0')
        ->assertJsonPath('status.bay_count', 4)
        ->assertJsonPath('protocol_version', '0.1.0')
        ->assertJsonStructure([
            'station_id',
            'mqtt' => ['host', 'port_tls', 'port_plain', 'username'],
            'topics' => ['publish', 'subscribe'],
            'status' => ['connected', 'firmware_version', 'bay_count'],
        ]);
});

test('GET /api/v1/station requires authentication', function (): void {
    $this->getJson('/api/v1/station')->assertStatus(401);
});

test('POST /api/v1/station/regenerate-password returns new password', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    $response = $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/station/regenerate-password');

    $response->assertStatus(200)
        ->assertJsonStructure(['mqtt_password', 'message']);

    $newPassword = $response->json('mqtt_password');
    expect(strlen($newPassword))->toBe(32); // 16 bytes hex = 32 chars
});

test('GET /api/v1/station/status returns live state from Redis', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_status01',
    ]);

    $state = app(StationStateService::class);
    $state->resetState('stn_status01', 2);
    $state->setBayStatus('stn_status01', 1, 'Available');

    $response = $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/station/status');

    $response->assertStatus(200)
        ->assertJsonPath('connected', true)
        ->assertJsonPath('lifecycle', 'online')
        ->assertJsonCount(2, 'bays')
        ->assertJsonPath('bays.0.status', 'Available')
        ->assertJsonPath('bays.0.bay_number', 1);
});

<?php

declare(strict_types=1);

use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;
use Illuminate\Support\Facades\Http;

test('POST /api/v1/commands/{action} sends command and returns 202', function (): void {
    Http::fake(['*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_cmd_send_01',
        'is_connected' => true,
    ]);

    $response = $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/commands/Reset', ['type' => 'Soft']);

    $response->assertStatus(202)
        ->assertJsonPath('status', 'sent')
        ->assertJsonStructure(['command_id', 'message_id', 'status']);

    $this->assertDatabaseHas('command_history', [
        'station_id' => 'stn_cmd_send_01',
        'action' => 'Reset',
        'status' => 'sent',
    ]);
});

test('command fails when station not connected', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_cmd_disc_01',
        'is_connected' => false,
    ]);

    $response = $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/commands/Reset', ['type' => 'Soft']);

    $response->assertStatus(409)
        ->assertJsonPath('error', 'STATION_NOT_CONNECTED');
});

test('command fails with invalid action', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_cmd_inv_01',
        'is_connected' => true,
    ]);

    $response = $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/commands/FakeAction', []);

    $response->assertStatus(400)
        ->assertJsonPath('error', 'INVALID_ACTION');
});

test('command validates payload schema', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_cmd_val_01',
        'is_connected' => true,
    ]);

    // Reset requires resetType — send empty payload to trigger schema validation failure
    $response = $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/commands/Reset', []);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'VALIDATION_ERROR');
});

test('command requires authentication', function (): void {
    $this->postJson('/api/v1/commands/Reset', ['type' => 'Soft'])
        ->assertStatus(401);
});

test('GET /api/v1/commands/history returns command list', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_cmd_hist_01',
    ]);

    CommandHistory::factory()->for($tenant)->create([
        'station_id' => 'stn_cmd_hist_01',
        'action' => 'Reset',
        'status' => 'sent',
    ]);
    CommandHistory::factory()->for($tenant)->create([
        'station_id' => 'stn_cmd_hist_01',
        'action' => 'StartService',
        'status' => 'responded',
    ]);

    $response = $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/commands/history');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'commands')
        ->assertJsonStructure([
            'commands' => [
                '*' => ['id', 'action', 'status'],
            ],
        ]);
});

test('GET /api/v1/commands/{action}/schema returns schema', function (): void {
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/commands/StartService/schema');

    $response->assertStatus(200)
        ->assertJsonPath('action', 'StartService')
        ->assertJsonStructure(['action', 'schema']);
});

test('GET /api/v1/commands/{action}/schema returns 404 for unknown action', function (): void {
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/commands/FakeAction/schema');

    $response->assertStatus(404);
});

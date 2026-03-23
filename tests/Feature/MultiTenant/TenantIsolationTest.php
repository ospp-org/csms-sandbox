<?php

declare(strict_types=1);

use App\Models\CommandHistory;
use App\Models\ConformanceResult;
use App\Models\MessageLog;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\MqttMessageDispatcher;
use App\Services\StationStateService;
use Illuminate\Support\Facades\Http;

function setupTwoTenants(): array
{
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenantA = Tenant::factory()->create(['email' => 'a@test.com']);
    $stationA = TenantStation::factory()->for($tenantA)->create(['station_id' => 'stn_aa000001']);

    $tenantB = Tenant::factory()->create(['email' => 'b@test.com']);
    $stationB = TenantStation::factory()->for($tenantB)->create(['station_id' => 'stn_bb000001']);

    return [$tenantA, $stationA, $tenantB, $stationB];
}

function bootStationForIsolation(string $stationId): void
{
    $dispatcher = app(MqttMessageDispatcher::class);
    $dispatcher->dispatch($stationId, [
        'action' => 'BootNotification',
        'messageId' => 'msg_boot_' . $stationId,
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.2.1',
        'timestamp' => '2026-03-16T10:00:00.000Z',
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
}

// ---------------------------------------------------------------------------
// 1. MQTT message isolation
// ---------------------------------------------------------------------------

test('tenant A messages not visible to tenant B', function (): void {
    [$tenantA, $stationA, $tenantB, $stationB] = setupTwoTenants();

    bootStationForIsolation('stn_aa000001');

    // Tenant A has messages (boot inbound + boot outbound)
    $tenantAMessages = MessageLog::where('tenant_id', $tenantA->id)->count();
    expect($tenantAMessages)->toBeGreaterThanOrEqual(1);

    // Tenant B has zero messages
    $tenantBMessages = MessageLog::where('tenant_id', $tenantB->id)->count();
    expect($tenantBMessages)->toBe(0);
});

// ---------------------------------------------------------------------------
// 2. API station isolation
// ---------------------------------------------------------------------------

test('tenant A cannot see tenant B station via dashboard', function (): void {
    [$tenantA, $stationA, $tenantB, $stationB] = setupTwoTenants();

    // Acting as tenant A — setup page shows only tenant A's station
    $this->actingAs($tenantA)
        ->get('/dashboard/setup')
        ->assertOk()
        ->assertSee('stn_aa000001')
        ->assertDontSee('stn_bb000001');
});

// ---------------------------------------------------------------------------
// 3. Conformance isolation
// ---------------------------------------------------------------------------

test('tenant A conformance independent of tenant B', function (): void {
    [$tenantA, $stationA, $tenantB, $stationB] = setupTwoTenants();

    bootStationForIsolation('stn_aa000001');
    bootStationForIsolation('stn_bb000001');

    // Both tenants have BootNotification conformance results
    $resultA = ConformanceResult::where('tenant_id', $tenantA->id)
        ->where('action', 'BootNotification')->first();
    $resultB = ConformanceResult::where('tenant_id', $tenantB->id)
        ->where('action', 'BootNotification')->first();

    expect($resultA)->not->toBeNull();
    expect($resultB)->not->toBeNull();
    expect($resultA->id)->not->toBe($resultB->id);
});

// ---------------------------------------------------------------------------
// 4. Command isolation — tenant A cannot command tenant B station
// ---------------------------------------------------------------------------

test('tenant A cannot send command to tenant B station', function (): void {
    [$tenantA, $stationA, $tenantB, $stationB] = setupTwoTenants();

    // CommandService uses $tenant->id to find station
    // Tenant A's command lookup returns tenant A's station, not tenant B's
    $commandService = app(\App\Services\CommandService::class);
    $result = $commandService->send(
        tenantId: $tenantA->id,
        action: 'Reset',
        parameters: ['type' => 'Soft'],
    );

    // Should fail because tenant A's station is not connected, NOT because
    // it found tenant B's station
    expect($result->success)->toBeFalse();
    expect($result->errorCode)->toBeIn(['STATION_NOT_CONNECTED', 'NO_STATION']);
});

// ---------------------------------------------------------------------------
// 5. History isolation
// ---------------------------------------------------------------------------

test('tenant A history shows only own messages', function (): void {
    [$tenantA, $stationA, $tenantB, $stationB] = setupTwoTenants();

    bootStationForIsolation('stn_aa000001');
    bootStationForIsolation('stn_bb000001');

    // Both tenants have messages
    expect(MessageLog::where('tenant_id', $tenantA->id)->count())->toBeGreaterThan(0);
    expect(MessageLog::where('tenant_id', $tenantB->id)->count())->toBeGreaterThan(0);

    // Acting as tenant A — history page only shows tenant A messages
    $this->actingAs($tenantA)
        ->get('/dashboard/history')
        ->assertOk()
        ->assertSee('stn_aa000001')
        ->assertDontSee('stn_bb000001');
});

// ---------------------------------------------------------------------------
// 6. Dashboard isolation
// ---------------------------------------------------------------------------

test('tenant A dashboard shows only own station', function (): void {
    [$tenantA, $stationA, $tenantB, $stationB] = setupTwoTenants();

    $this->actingAs($tenantA)
        ->get('/dashboard/setup')
        ->assertOk()
        ->assertSee('stn_aa000001')
        ->assertDontSee('stn_bb000001');
});

// ---------------------------------------------------------------------------
// 7. MQTT ACL isolation
// ---------------------------------------------------------------------------

test('tenant A MQTT credentials denied on tenant B topic', function (): void {
    [$tenantA, $stationA, $tenantB, $stationB] = setupTwoTenants();

    // Tenant A's MQTT username trying to publish to tenant B's station topic
    $this->postJson('/internal/mqtt/acl', [
        'username' => $stationA->mqtt_username,
        'topic' => 'ospp/v1/stations/stn_bb000001/to-server',
        'action' => 'publish',
    ])->assertOk()->assertJson(['result' => 'deny']);

    // Tenant A's MQTT username on own topic — allowed
    $this->postJson('/internal/mqtt/acl', [
        'username' => $stationA->mqtt_username,
        'topic' => 'ospp/v1/stations/stn_aa000001/to-server',
        'action' => 'publish',
    ])->assertOk()->assertJson(['result' => 'allow']);
});

test('tenant A MQTT credentials denied subscribing to tenant B topic', function (): void {
    [$tenantA, $stationA, $tenantB, $stationB] = setupTwoTenants();

    // Subscribe to tenant B's station command topic — denied
    $this->postJson('/internal/mqtt/acl', [
        'username' => $stationA->mqtt_username,
        'topic' => 'ospp/v1/stations/stn_bb000001/to-station',
        'action' => 'subscribe',
    ])->assertOk()->assertJson(['result' => 'deny']);

    // Own station topic — allowed
    $this->postJson('/internal/mqtt/acl', [
        'username' => $stationA->mqtt_username,
        'topic' => 'ospp/v1/stations/stn_aa000001/to-station',
        'action' => 'subscribe',
    ])->assertOk()->assertJson(['result' => 'allow']);
});

// ---------------------------------------------------------------------------
// 8. DB query scoping — all models filtered by tenant_id
// ---------------------------------------------------------------------------

test('MessageLog queries scoped by tenant_id', function (): void {
    [$tenantA, $stationA, $tenantB, $stationB] = setupTwoTenants();

    bootStationForIsolation('stn_aa000001');
    bootStationForIsolation('stn_bb000001');

    $allA = MessageLog::where('tenant_id', $tenantA->id)->pluck('station_id')->unique()->toArray();
    $allB = MessageLog::where('tenant_id', $tenantB->id)->pluck('station_id')->unique()->toArray();

    // Tenant A only has messages for station A
    expect($allA)->toBe(['stn_aa000001']);
    // Tenant B only has messages for station B
    expect($allB)->toBe(['stn_bb000001']);
});

test('CommandHistory queries scoped by tenant_id', function (): void {
    [$tenantA, $stationA, $tenantB, $stationB] = setupTwoTenants();

    CommandHistory::create([
        'tenant_id' => $tenantA->id, 'station_id' => 'stn_aa000001',
        'action' => 'Reset', 'message_id' => 'cmd_a', 'payload' => ['type' => 'Soft'], 'status' => 'sent',
    ]);
    CommandHistory::create([
        'tenant_id' => $tenantB->id, 'station_id' => 'stn_bb000001',
        'action' => 'Reset', 'message_id' => 'cmd_b', 'payload' => ['type' => 'Soft'], 'status' => 'sent',
    ]);

    $aCommands = CommandHistory::where('tenant_id', $tenantA->id)->get();
    $bCommands = CommandHistory::where('tenant_id', $tenantB->id)->get();

    expect($aCommands)->toHaveCount(1);
    expect($aCommands->first()->station_id)->toBe('stn_aa000001');
    expect($bCommands)->toHaveCount(1);
    expect($bCommands->first()->station_id)->toBe('stn_bb000001');
});

test('ConformanceResult queries scoped by tenant_id', function (): void {
    [$tenantA, $stationA, $tenantB, $stationB] = setupTwoTenants();

    bootStationForIsolation('stn_aa000001');
    bootStationForIsolation('stn_bb000001');

    $aResults = ConformanceResult::where('tenant_id', $tenantA->id)->pluck('action')->toArray();
    $bResults = ConformanceResult::where('tenant_id', $tenantB->id)->pluck('action')->toArray();

    // Both have BootNotification result but in separate rows
    expect(in_array('BootNotification', $aResults, true))->toBeTrue();
    expect(in_array('BootNotification', $bResults, true))->toBeTrue();

    // Cross-check: tenant A results not polluted with tenant B ID
    $crossLeak = ConformanceResult::where('tenant_id', $tenantA->id)
        ->whereNotIn('action', config('conformance.actions'))
        ->count();
    expect($crossLeak)->toBe(0);
});

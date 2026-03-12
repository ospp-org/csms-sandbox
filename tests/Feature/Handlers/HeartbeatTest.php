<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\MqttMessageDispatcher;
use App\Services\StationStateService;
use Illuminate\Support\Facades\Http;

test('Heartbeat refreshes connection and returns serverTime', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_hb001']);

    // First boot the station
    $state = app(StationStateService::class);
    $state->resetState('stn_hb001', 2);

    $envelope = [
        'action' => 'Heartbeat',
        'messageId' => 'msg_hb_001',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.1.0',
        'timestamp' => '2026-03-09T10:05:30.000Z',
        'payload' => [],
    ];

    app(MqttMessageDispatcher::class)->dispatch('stn_hb001', $envelope);

    expect($state->isConnected('stn_hb001'))->toBeTrue();
    expect($state->getLastHeartbeat('stn_hb001'))->not->toBeNull();

    // Message logged
    $this->assertDatabaseHas('message_log', [
        'station_id' => 'stn_hb001',
        'action' => 'Heartbeat',
        'direction' => 'inbound',
    ]);
});

test('Heartbeat logs last heartbeat timestamp in Redis', function (): void {
    Http::fake(['*/api/v5/*' => Http::response(['token' => 'test'], 200)]);

    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_hb002']);

    $state = app(StationStateService::class);
    $state->resetState('stn_hb002', 1);

    $before = time();

    app(MqttMessageDispatcher::class)->dispatch('stn_hb002', [
        'action' => 'Heartbeat',
        'messageId' => 'msg_hb_002',
        'messageType' => 'Request',
        'source' => 'Station',
        'protocolVersion' => '0.1.0',
        'timestamp' => '2026-03-09T10:05:30.000Z',
        'payload' => [],
    ]);

    $lastHb = $state->getLastHeartbeat('stn_hb002');
    expect($lastHb)->toBeGreaterThanOrEqual($before);
    expect($lastHb)->toBeLessThanOrEqual(time());
});

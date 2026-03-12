<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;

test('ACL allows publish to own station to-server topic', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create([
        'mqtt_username' => 'sandbox_acl',
        'station_id' => 'stn_00000099',
    ]);

    $response = $this->postJson('/internal/mqtt/acl', [
        'username' => 'sandbox_acl',
        'topic' => 'ospp/v1/stations/stn_00000099/to-server',
        'action' => 'publish',
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')]);

    $response->assertJsonPath('result', 'allow');
});

test('ACL allows subscribe to own station to-station topic', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'mqtt_username' => 'sandbox_acl',
        'station_id' => 'stn_00000099',
    ]);

    $response = $this->postJson('/internal/mqtt/acl', [
        'username' => 'sandbox_acl',
        'topic' => 'ospp/v1/stations/stn_00000099/to-station',
        'action' => 'subscribe',
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')]);

    $response->assertJsonPath('result', 'allow');
});

test('ACL denies publish to other station topic', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'mqtt_username' => 'sandbox_acl',
        'station_id' => 'stn_00000099',
    ]);

    $response = $this->postJson('/internal/mqtt/acl', [
        'username' => 'sandbox_acl',
        'topic' => 'ospp/v1/stations/stn_00000001/to-server',
        'action' => 'publish',
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')]);

    $response->assertJsonPath('result', 'deny');
});

test('ACL denies subscribe to other station topic', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'mqtt_username' => 'sandbox_acl',
        'station_id' => 'stn_00000099',
    ]);

    $response = $this->postJson('/internal/mqtt/acl', [
        'username' => 'sandbox_acl',
        'topic' => 'ospp/v1/stations/stn_00000001/to-station',
        'action' => 'subscribe',
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')]);

    $response->assertJsonPath('result', 'deny');
});

test('ACL denies unknown username', function (): void {
    $response = $this->postJson('/internal/mqtt/acl', [
        'username' => 'unknown',
        'topic' => 'ospp/v1/stations/stn_00000001/to-server',
        'action' => 'publish',
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')]);

    $response->assertJsonPath('result', 'deny');
});

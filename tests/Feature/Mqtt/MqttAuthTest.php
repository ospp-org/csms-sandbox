<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;
use Illuminate\Support\Facades\Hash;

test('POST /internal/mqtt/auth allows valid credentials', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'mqtt_username' => 'sandbox_test',
        'mqtt_password_hash' => Hash::make('test-password'),
    ]);

    $response = $this->postJson('/internal/mqtt/auth', [
        'username' => 'sandbox_test',
        'password' => 'test-password',
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')]);

    $response->assertStatus(200)
        ->assertJsonPath('result', 'allow')
        ->assertJsonPath('is_superuser', false);
});

test('POST /internal/mqtt/auth denies wrong password', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'mqtt_username' => 'sandbox_test',
        'mqtt_password_hash' => Hash::make('correct-password'),
    ]);

    $response = $this->postJson('/internal/mqtt/auth', [
        'username' => 'sandbox_test',
        'password' => 'wrong-password',
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')]);

    $response->assertStatus(200)
        ->assertJsonPath('result', 'deny');
});

test('POST /internal/mqtt/auth denies unknown username', function (): void {
    $response = $this->postJson('/internal/mqtt/auth', [
        'username' => 'nonexistent',
        'password' => 'anything',
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')]);

    $response->assertStatus(200)
        ->assertJsonPath('result', 'deny');
});

test('POST /internal/mqtt/auth works without webhook secret header (protected by nginx IP restriction)', function (): void {
    $this->postJson('/internal/mqtt/auth', [
        'username' => 'sandbox_test',
        'password' => 'test-password',
    ])->assertStatus(200)
        ->assertJsonPath('result', 'deny');
});

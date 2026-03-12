<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;

test('POST /api/v1/auth/register creates tenant with station and returns JWT', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'token',
            'tenant' => ['id', 'name', 'email', 'protocol_version', 'validation_mode'],
            'station' => ['station_id', 'mqtt_host', 'mqtt_port', 'mqtt_username', 'mqtt_password'],
        ])
        ->assertJsonPath('tenant.email', 'test@example.com')
        ->assertJsonPath('tenant.validation_mode', 'strict');

    $this->assertDatabaseHas('tenants', ['email' => 'test@example.com']);
    $this->assertDatabaseHas('tenant_stations', ['tenant_id' => $response->json('tenant.id')]);

    // Conformance results should be seeded
    expect($this->app->make('db')->table('conformance_results')
        ->where('tenant_id', $response->json('tenant.id'))
        ->count())->toBe(26);
});

test('POST /api/v1/auth/register fails with duplicate email', function (): void {
    Tenant::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('POST /api/v1/auth/register fails with weak password', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('POST /api/v1/auth/register fails without name', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('register returns JWT token that can authenticate', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $token = $response->json('token');

    $this->getJson('/api/v1/station', [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(200);
});

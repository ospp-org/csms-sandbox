<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;

test('POST /api/v1/auth/login returns JWT for valid credentials', function (): void {
    Tenant::factory()->create([
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'tenant' => ['id', 'name', 'email']])
        ->assertJsonPath('tenant.email', 'user@example.com');
});

test('POST /api/v1/auth/login fails with wrong password', function (): void {
    Tenant::factory()->create([
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'INVALID_CREDENTIALS');
});

test('POST /api/v1/auth/login fails with nonexistent email', function (): void {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'INVALID_CREDENTIALS');
});

test('login returns JWT token that can authenticate', function (): void {
    $tenant = Tenant::factory()->create([
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);
    TenantStation::factory()->for($tenant)->create();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);

    $token = $response->json('token');

    $this->getJson('/api/v1/station', [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(200)->assertJsonStructure(['station_id']);
});

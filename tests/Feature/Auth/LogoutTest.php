<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;

test('POST /api/v1/auth/logout returns success', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    $response = $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Logged out');
});

test('POST /api/v1/auth/logout fails without auth', function (): void {
    $this->postJson('/api/v1/auth/logout')
        ->assertStatus(401);
});

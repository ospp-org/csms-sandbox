<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;

test('login page is accessible', function (): void {
    $this->get('/login')->assertStatus(200)->assertSee('Login');
});

test('register page is accessible', function (): void {
    $this->get('/register')->assertStatus(200)->assertSee('Create Account');
});

test('user can register and is redirected to setup', function (): void {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test-web@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('/dashboard/setup');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('tenants', ['email' => 'test-web@example.com']);
});

test('user can login with valid credentials', function (): void {
    $tenant = Tenant::factory()->create(['password' => 'password123']);
    TenantStation::factory()->for($tenant)->create();

    $response = $this->post('/login', [
        'email' => $tenant->email,
        'password' => 'password123',
    ]);

    $response->assertRedirect('/dashboard/setup');
    $this->assertAuthenticatedAs($tenant);
});

test('login fails with invalid credentials', function (): void {
    $tenant = Tenant::factory()->create();

    $response = $this->post('/login', [
        'email' => $tenant->email,
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect();
    $this->assertGuest();
});

test('user can logout', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    $this->actingAs($tenant)
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});

test('authenticated user is redirected from login page', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    $this->actingAs($tenant)
        ->get('/login')
        ->assertRedirect();
});

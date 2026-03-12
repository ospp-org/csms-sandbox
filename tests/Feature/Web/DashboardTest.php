<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;

test('setup page requires auth', function (): void {
    $this->get('/dashboard/setup')->assertRedirect('/login');
});

test('setup page renders with MQTT credentials', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create();

    $this->actingAs($tenant)
        ->get('/dashboard/setup')
        ->assertStatus(200)
        ->assertSee('MQTT Connection')
        ->assertSee($station->mqtt_username)
        ->assertSee($station->station_id);
});

test('monitor page is accessible when authenticated', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    $this->actingAs($tenant)
        ->get('/dashboard/monitor')
        ->assertStatus(200)
        ->assertSee('Live Messages');
});

test('commands page is accessible when authenticated', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    $this->actingAs($tenant)
        ->get('/dashboard/commands')
        ->assertStatus(200)
        ->assertSee('Command Center');
});

test('conformance page is accessible when authenticated', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    $this->actingAs($tenant)
        ->get('/dashboard/conformance')
        ->assertStatus(200)
        ->assertSee('Conformance Report');
});

test('history page is accessible when authenticated', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    $this->actingAs($tenant)
        ->get('/dashboard/history')
        ->assertStatus(200)
        ->assertSee('Message History');
});

test('settings page is accessible when authenticated', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    $this->actingAs($tenant)
        ->get('/dashboard/settings')
        ->assertStatus(200)
        ->assertSee('Settings');
});

test('settings can be updated', function (): void {
    $tenant = Tenant::factory()->create(['validation_mode' => 'strict']);
    TenantStation::factory()->for($tenant)->create();

    $this->actingAs($tenant)
        ->patch('/dashboard/settings', ['validation_mode' => 'lenient'])
        ->assertRedirect('/dashboard/settings');

    $tenant->refresh();
    expect($tenant->validation_mode)->toBe('lenient');
});

test('all dashboard pages require auth', function (): void {
    $pages = ['/dashboard/setup', '/dashboard/monitor', '/dashboard/commands', '/dashboard/conformance', '/dashboard/history', '/dashboard/settings'];

    foreach ($pages as $page) {
        $this->get($page)->assertRedirect('/login');
    }
});

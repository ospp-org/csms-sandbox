<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;

test('returns stations with certs for authenticated tenant', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_ce200001',
        'station_cert' => "-----BEGIN CERTIFICATE-----\ncert1\n-----END CERTIFICATE-----",
        'station_key' => "-----BEGIN EC PRIVATE KEY-----\nkey1\n-----END EC PRIVATE KEY-----",
    ]);
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_ce200002',
        'station_cert' => "-----BEGIN CERTIFICATE-----\ncert2\n-----END CERTIFICATE-----",
        'station_key' => "-----BEGIN EC PRIVATE KEY-----\nkey2\n-----END EC PRIVATE KEY-----",
    ]);

    $response = $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/simulator/certificates');

    $response->assertOk()
        ->assertJsonStructure([
            'sandbox_deviation',
            'ca',
            'stations' => [
                'stn_ce200001' => ['cert', 'key'],
                'stn_ce200002' => ['cert', 'key'],
            ],
        ])
        ->assertJsonPath('stations.stn_ce200001.cert', "-----BEGIN CERTIFICATE-----\ncert1\n-----END CERTIFICATE-----")
        ->assertJsonPath('stations.stn_ce200002.cert', "-----BEGIN CERTIFICATE-----\ncert2\n-----END CERTIFICATE-----");
});

test('excludes stations without certificates', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_ce200003',
        'station_cert' => "-----BEGIN CERTIFICATE-----\ncert\n-----END CERTIFICATE-----",
        'station_key' => "-----BEGIN EC PRIVATE KEY-----\nkey\n-----END EC PRIVATE KEY-----",
    ]);
    TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_ce200004',
        'station_cert' => null,
        'station_key' => null,
    ]);

    $response = $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/simulator/certificates');

    $response->assertOk();
    $stations = $response->json('stations');
    expect($stations)->toHaveKey('stn_ce200003');
    expect($stations)->not->toHaveKey('stn_ce200004');
});

test('does not return other tenant stations', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    TenantStation::factory()->for($tenantA)->create([
        'station_id' => 'stn_ce200005',
        'station_cert' => 'cert-a',
        'station_key' => 'key-a',
    ]);
    TenantStation::factory()->for($tenantB)->create([
        'station_id' => 'stn_ce200006',
        'station_cert' => 'cert-b',
        'station_key' => 'key-b',
    ]);

    $response = $this->actingAs($tenantA, 'jwt')
        ->getJson('/api/v1/simulator/certificates');

    $response->assertOk();
    $stations = $response->json('stations');
    expect($stations)->toHaveKey('stn_ce200005');
    expect($stations)->not->toHaveKey('stn_ce200006');
});

test('requires authentication', function (): void {
    $this->getJson('/api/v1/simulator/certificates')
        ->assertStatus(401);
});

<?php

declare(strict_types=1);

use App\Contracts\CertificateServiceInterface;
use App\Models\Tenant;
use App\Models\TenantStation;

test('outputs PKI not configured when isConfigured returns false', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_cert' => null]);
    TenantStation::factory()->for($tenant)->create(['station_cert' => null]);

    // Default CertificateService returns false (no PKI files in test env)
    $this->artisan('certificates:generate-missing')
        ->expectsOutputToContain('PKI not configured. Skipped 2 station(s).')
        ->assertExitCode(1);
});

test('generates certs for stations with null cert when PKI configured', function (): void {
    $tenant = Tenant::factory()->create();
    $stationA = TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_ce100001',
        'station_cert' => null,
    ]);
    $stationB = TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_ce100002',
        'station_cert' => 'existing-cert',
    ]);

    $mock = Mockery::mock(CertificateServiceInterface::class);
    $mock->shouldReceive('isConfigured')->once()->andReturn(true);
    $mock->shouldReceive('generateStationCert')->once()->with(
        Mockery::on(fn ($s) => $s->station_id === 'stn_ce100001')
    );
    $this->app->instance(CertificateServiceInterface::class, $mock);

    $this->artisan('certificates:generate-missing')
        ->expectsOutputToContain('Generated cert for stn_ce100001')
        ->expectsOutputToContain('Done. Generated: 1, Total: 1')
        ->assertExitCode(0);
});

test('reports all stations have certs when none missing', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_cert' => 'has-cert']);

    $mock = Mockery::mock(CertificateServiceInterface::class);
    $mock->shouldReceive('isConfigured')->once()->andReturn(true);
    $this->app->instance(CertificateServiceInterface::class, $mock);

    $this->artisan('certificates:generate-missing')
        ->expectsOutputToContain('All stations already have certificates.')
        ->assertExitCode(0);
});

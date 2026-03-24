<?php

declare(strict_types=1);

use App\Contracts\CertificateServiceInterface;
use App\Models\Tenant;
use App\Models\TenantStation;

test('download ca returns PEM when chain available', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    $mock = Mockery::mock(CertificateServiceInterface::class);
    $mock->shouldReceive('getCaChain')->once()->andReturn("-----BEGIN CERTIFICATE-----\nfake-ca-chain\n-----END CERTIFICATE-----");
    $this->app->instance(CertificateServiceInterface::class, $mock);

    $this->actingAs($tenant)
        ->get('/dashboard/certificates/ca')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/x-pem-file')
        ->assertHeader('Content-Disposition', 'attachment; filename="ospp-sandbox-ca.pem"')
        ->assertSee('fake-ca-chain');
});

test('download cert returns station certificate PEM', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_cert' => "-----BEGIN CERTIFICATE-----\nfake-station-cert\n-----END CERTIFICATE-----",
    ]);

    $this->actingAs($tenant)
        ->get('/dashboard/certificates/cert')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/x-pem-file')
        ->assertHeader('Content-Disposition', 'attachment; filename="station.pem"')
        ->assertSee('fake-station-cert');
});

test('download key returns station private key PEM', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create([
        'station_key' => "-----BEGIN EC PRIVATE KEY-----\nfake-key\n-----END EC PRIVATE KEY-----",
    ]);

    $this->actingAs($tenant)
        ->get('/dashboard/certificates/key')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/x-pem-file')
        ->assertHeader('Content-Disposition', 'attachment; filename="station-key.pem"')
        ->assertSee('fake-key');
});

test('download cert redirects with error when cert is null', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_cert' => null]);

    $this->actingAs($tenant)
        ->get('/dashboard/certificates/cert')
        ->assertRedirect('/dashboard/setup')
        ->assertSessionHas('error', 'Certificate not yet generated.');
});

test('download key redirects with error when key is null', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_key' => null]);

    $this->actingAs($tenant)
        ->get('/dashboard/certificates/key')
        ->assertRedirect('/dashboard/setup')
        ->assertSessionHas('error', 'Certificate not yet generated.');
});

test('download ca redirects with error when chain file missing', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    // PKI not configured in test env — chain file doesn't exist
    $this->actingAs($tenant)
        ->get('/dashboard/certificates/ca')
        ->assertRedirect('/dashboard/setup')
        ->assertSessionHas('error');
});

test('regenerate succeeds when PKI configured', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    $mock = Mockery::mock(CertificateServiceInterface::class);
    $mock->shouldReceive('isConfigured')->once()->andReturn(true);
    $mock->shouldReceive('revokeCert')->once();
    $mock->shouldReceive('generateStationCert')->once();
    $this->app->instance(CertificateServiceInterface::class, $mock);

    $this->actingAs($tenant)
        ->post('/dashboard/certificates/regenerate')
        ->assertRedirect('/dashboard/setup')
        ->assertSessionHas('success', 'Station certificate regenerated.');
});

test('regenerate redirects with error when PKI not configured', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    // PKI not configured in test env
    $this->actingAs($tenant)
        ->post('/dashboard/certificates/regenerate')
        ->assertRedirect('/dashboard/setup')
        ->assertSessionHas('error');
});

test('certificate routes require authentication', function (): void {
    $this->get('/dashboard/certificates/ca')->assertRedirect('/login');
    $this->get('/dashboard/certificates/cert')->assertRedirect('/login');
    $this->get('/dashboard/certificates/key')->assertRedirect('/login');
    $this->post('/dashboard/certificates/regenerate')->assertRedirect('/login');
});

test('invalid file parameter returns 404', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create();

    $this->actingAs($tenant)
        ->get('/dashboard/certificates/invalid')
        ->assertNotFound();
});

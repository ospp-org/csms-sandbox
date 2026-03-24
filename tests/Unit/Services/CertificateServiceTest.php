<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\CertificateService;

beforeEach(function (): void {
    $caCert = (string) config('sandbox.pki.station_ca_cert');
    $caKey = (string) config('sandbox.pki.station_ca_key');

    if (! file_exists($caCert) || ! file_exists($caKey)) {
        $this->markTestSkipped('PKI files not present — skipping cert generation tests');
    }
});

test('generates valid ECDSA P-256 certificate with correct CN', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_ce000001']);

    $service = app(CertificateService::class);
    $result = $service->generateStationCert($station);

    expect($result['cert'])->toContain('-----BEGIN CERTIFICATE-----');
    expect($result['key'])->toContain('-----BEGIN EC PRIVATE KEY-----')
        ->or(fn ($v) => $v->toContain('-----BEGIN PRIVATE KEY-----'));
    expect($result['expires_at'])->toBeInstanceOf(\Illuminate\Support\Carbon::class);

    // Parse cert and verify CN
    $certResource = openssl_x509_read($result['cert']);
    expect($certResource)->not->toBeFalse();

    $certInfo = openssl_x509_parse($certResource);
    expect($certInfo['subject']['CN'])->toBe('stn_ce000001');

    // Verify key is ECDSA P-256
    $keyResource = openssl_pkey_get_private($result['key']);
    $keyDetails = openssl_pkey_get_details($keyResource);
    expect($keyDetails['type'])->toBe(OPENSSL_KEYTYPE_EC);
    expect($keyDetails['ec']['curve_name'])->toBe('prime256v1');
});

test('cert is signed by Station CA', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_ce000002']);

    $service = app(CertificateService::class);
    $result = $service->generateStationCert($station);

    $caCert = file_get_contents((string) config('sandbox.pki.station_ca_cert'));

    // Verify certificate was signed by the CA
    $verified = openssl_x509_verify($result['cert'], $caCert);
    expect($verified)->toBe(1);
});

test('stores cert data on station record', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_ce000003']);

    $service = app(CertificateService::class);
    $service->generateStationCert($station);

    $station->refresh();
    expect($station->station_cert)->toContain('-----BEGIN CERTIFICATE-----');
    expect($station->station_key)->toContain('-----BEGIN');
    expect($station->cert_issued_at)->not->toBeNull();
    expect($station->cert_expires_at)->not->toBeNull();
});

test('revokeCert clears cert columns', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_ce000004',
        'station_cert' => 'fake-cert',
        'station_key' => 'fake-key',
        'cert_issued_at' => now(),
        'cert_expires_at' => now()->addYear(),
    ]);

    $service = app(CertificateService::class);
    $service->revokeCert($station);

    $station->refresh();
    expect($station->station_cert)->toBeNull();
    expect($station->station_key)->toBeNull();
    expect($station->cert_issued_at)->toBeNull();
    expect($station->cert_expires_at)->toBeNull();
});

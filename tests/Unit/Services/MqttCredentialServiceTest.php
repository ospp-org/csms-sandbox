<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\MqttCredentialService;
use Illuminate\Support\Facades\Hash;

test('generateForTenant creates station with valid credentials', function (): void {
    $tenant = Tenant::factory()->create();
    $service = app(MqttCredentialService::class);

    $station = $service->generateForTenant($tenant);

    expect($station)->toBeInstanceOf(TenantStation::class);
    expect($station->tenant_id)->toBe($tenant->id);
    expect($station->station_id)->toStartWith('stn_');
    expect($station->mqtt_username)->toStartWith('sandbox_');
    expect($station->mqtt_password_hash)->not->toBeEmpty();
    expect($station->mqtt_password_encrypted)->not->toBeEmpty();
});

test('generated MQTT password can be verified', function (): void {
    $tenant = Tenant::factory()->create();
    $service = app(MqttCredentialService::class);

    $station = $service->generateForTenant($tenant);
    $plainPassword = $service->getPlainPassword($station);

    expect(Hash::check($plainPassword, $station->mqtt_password_hash))->toBeTrue();
});

test('regeneratePassword returns new working password', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create();

    $service = app(MqttCredentialService::class);
    $newPassword = $service->regeneratePassword($station);

    $station->refresh();
    expect(Hash::check($newPassword, $station->mqtt_password_hash))->toBeTrue();
    expect(strlen($newPassword))->toBe(32);
});

test('station IDs are unique and sequential', function (): void {
    $service = app(MqttCredentialService::class);

    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $station1 = $service->generateForTenant($tenant1);
    $station2 = $service->generateForTenant($tenant2);

    expect($station1->station_id)->not->toBe($station2->station_id);
});

<?php

declare(strict_types=1);

use App\Services\SchemaValidationService;

test('validates valid BootNotification payload', function (): void {
    $service = app(SchemaValidationService::class);

    $result = $service->validate('BootNotification', 'Request', [
        'stationId' => 'stn_00000001',
        'firmwareVersion' => '1.0.0',
        'stationModel' => 'WashPro 5000',
        'stationVendor' => 'TestVendor',
        'serialNumber' => 'SN123456',
        'bayCount' => 4,
        'uptimeSeconds' => 120,
        'pendingOfflineTransactions' => 0,
        'timezone' => 'Europe/Bucharest',
        'bootReason' => 'PowerOn',
        'capabilities' => [
            'bleSupported' => false,
            'offlineModeSupported' => false,
            'meterValuesSupported' => true,
        ],
        'networkInfo' => [
            'connectionType' => 'Ethernet',
        ],
    ]);

    expect($result->valid)->toBeTrue();
});

test('rejects BootNotification missing required fields', function (): void {
    $service = app(SchemaValidationService::class);

    $result = $service->validate('BootNotification', 'Request', [
        'stationVendor' => 'Test',
        'firmwareVersion' => '1.0.0',
    ]);

    expect($result->valid)->toBeFalse();
    expect($result->errors)->not->toBeEmpty();
});

test('validates valid StatusNotification payload', function (): void {
    $service = app(SchemaValidationService::class);

    $result = $service->validate('StatusNotification', 'Event', [
        'bayId' => 'bay_00000001',
        'bayNumber' => 1,
        'status' => 'Available',
        'services' => [
            ['serviceId' => 'svc_wash', 'available' => true],
        ],
    ]);

    expect($result->valid)->toBeTrue();
});

test('rejects StatusNotification with invalid status', function (): void {
    $service = app(SchemaValidationService::class);

    $result = $service->validate('StatusNotification', 'Event', [
        'bayId' => 'bay_00000001',
        'bayNumber' => 1,
        'status' => 'InvalidStatus',
        'services' => [
            ['serviceId' => 'svc_wash', 'available' => true],
        ],
    ]);

    expect($result->valid)->toBeFalse();
});

test('validates empty Heartbeat payload', function (): void {
    $service = app(SchemaValidationService::class);

    $result = $service->validate('Heartbeat', 'Request', []);

    expect($result->valid)->toBeTrue();
});

test('skips validation for unknown action', function (): void {
    $service = app(SchemaValidationService::class);

    $result = $service->validate('UnknownAction', 'Request', []);

    expect($result->valid)->toBeTrue();
    expect($result->skipped)->toBeTrue();
});

test('validates valid outbound StartService', function (): void {
    $service = app(SchemaValidationService::class);

    $result = $service->validateOutbound('StartService', [
        'sessionId' => 'sess_00000001',
        'bayId' => 'bay_00000001',
        'serviceId' => 'svc_wash',
        'durationSeconds' => 300,
        'sessionSource' => 'MobileApp',
    ]);

    expect($result->valid)->toBeTrue();
});

test('rejects outbound StartService missing serviceId', function (): void {
    $service = app(SchemaValidationService::class);

    $result = $service->validateOutbound('StartService', [
        'sessionId' => 'sess_00000001',
        'bayId' => 'bay_00000001',
        'durationSeconds' => 300,
        'sessionSource' => 'MobileApp',
    ]);

    expect($result->valid)->toBeFalse();
});

test('returns outbound schema', function (): void {
    $service = app(SchemaValidationService::class);

    $schema = $service->getOutboundSchema('Reset');

    expect($schema)->not->toBeNull();
    expect($schema)->toHaveKey('required');
});

test('returns null for unknown outbound action', function (): void {
    $service = app(SchemaValidationService::class);

    $schema = $service->getOutboundSchema('FakeAction');

    expect($schema)->toBeNull();
});

test('toJsonObject converts nested empty arrays to objects', function (): void {
    $service = app(SchemaValidationService::class);

    // DataTransfer 'data' is typed as object in SDK schema.
    // PHP sends ['data' => []] — toJsonObject must convert [] to {} for validation.
    $result = $service->validate('DataTransfer', 'Request', [
        'vendorId' => 'com.example',
        'dataId' => 'test',
        'data' => [],
    ]);

    expect($result->valid)->toBeTrue();
});

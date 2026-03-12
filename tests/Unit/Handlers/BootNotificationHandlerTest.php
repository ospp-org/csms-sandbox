<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\BootNotificationHandler;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('BootNotification returns Accepted with serverTime and heartbeatInterval', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_unit001']);

    $handler = app(BootNotificationHandler::class);

    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_unit001',
        action: 'BootNotification',
        messageId: 'msg_001',
        messageType: 'Request',
        payload: [
            'stationModel' => 'WashPro 5000',
            'stationVendor' => 'Test',
            'firmwareVersion' => '1.0.0',
            'bayCount' => 4,
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload['status'])->toBe('Accepted');
    expect($result->responsePayload)->toHaveKey('serverTime');
    expect($result->responsePayload)->toHaveKey('heartbeatIntervalSec');
    expect($result->responsePayload['heartbeatIntervalSec'])->toBe(30);
});

test('BootNotification sets lifecycle to online in Redis', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_unit002']);

    $handler = app(BootNotificationHandler::class);

    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_unit002',
        action: 'BootNotification',
        messageId: 'msg_002',
        messageType: 'Request',
        payload: ['stationModel' => 'T', 'stationVendor' => 'T', 'firmwareVersion' => '1.0.0', 'bayCount' => 2],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $handler->handle($context);

    $state = app(StationStateService::class);
    expect($state->getLifecycle('stn_unit002'))->toBe('online');
    expect($state->isConnected('stn_unit002'))->toBeTrue();
});

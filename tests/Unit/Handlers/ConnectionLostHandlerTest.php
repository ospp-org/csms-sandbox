<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\ConnectionLostHandler;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('sets station lifecycle to offline', function (): void {
    $stationState = app(StationStateService::class);
    $stationState->resetState('stn_cl01', 2);

    $handler = app(ConnectionLostHandler::class);
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_cl01',
        action: 'ConnectionLost',
        messageId: 'msg_cl_001',
        messageType: 'Event',
        payload: [
            'stationId' => 'stn_cl01',
            'reason' => 'UnexpectedDisconnect',
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
    expect($stationState->getLifecycle('stn_cl01'))->toBe('offline');
});

test('resets all bays to Unknown on disconnect', function (): void {
    $stationState = app(StationStateService::class);
    $stationState->resetState('stn_cl03', 3);
    $stationState->setBayStatus('stn_cl03', 1, 'Available');
    $stationState->setBayStatus('stn_cl03', 2, 'Occupied');
    $stationState->setBayStatus('stn_cl03', 3, 'Reserved');

    $handler = app(ConnectionLostHandler::class);
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_cl03',
        action: 'ConnectionLost',
        messageId: 'msg_cl_003',
        messageType: 'Event',
        payload: [
            'stationId' => 'stn_cl03',
            'reason' => 'UnexpectedDisconnect',
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $handler->handle($context);

    expect($stationState->getBayStatus('stn_cl03', 1))->toBe('Unknown');
    expect($stationState->getBayStatus('stn_cl03', 2))->toBe('Unknown');
    expect($stationState->getBayStatus('stn_cl03', 3))->toBe('Unknown');
});

test('updates is_connected to false in database', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create([
        'station_id' => 'stn_cl04',
        'is_connected' => true,
    ]);

    $stationState = app(StationStateService::class);
    $stationState->resetState('stn_cl04', 2);

    $handler = app(ConnectionLostHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_cl04',
        action: 'ConnectionLost',
        messageId: 'msg_cl_004',
        messageType: 'Event',
        payload: [
            'stationId' => 'stn_cl04',
            'reason' => 'UnexpectedDisconnect',
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $handler->handle($context);

    $station->refresh();
    expect($station->is_connected)->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\ConnectionLostHandler;
use App\Services\StationStateService;

test('sets station lifecycle to offline', function (): void {
    $stationState = app(StationStateService::class);
    $stationState->setLifecycle('stn_cl01', 'online');

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
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
    expect($stationState->getLifecycle('stn_cl01'))->toBe('offline');
});

test('returns acknowledged with no response', function (): void {
    $handler = app(ConnectionLostHandler::class);
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_cl02',
        action: 'ConnectionLost',
        messageId: 'msg_cl_002',
        messageType: 'Event',
        payload: [
            'stationId' => 'stn_cl02',
            'reason' => 'UnexpectedDisconnect',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

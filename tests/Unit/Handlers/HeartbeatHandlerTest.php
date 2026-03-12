<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\HeartbeatHandler;
use App\Services\StationStateService;

test('Heartbeat returns serverTime', function (): void {
    $handler = app(HeartbeatHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_hbunit01',
        action: 'Heartbeat',
        messageId: 'msg_hb_001',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toHaveKey('serverTime');
});

test('Heartbeat refreshes connection in Redis', function (): void {
    $handler = app(HeartbeatHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_hbunit02',
        action: 'Heartbeat',
        messageId: 'msg_hb_002',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $handler->handle($context);

    $state = app(StationStateService::class);
    expect($state->isConnected('stn_hbunit02'))->toBeTrue();
    expect($state->getLastHeartbeat('stn_hbunit02'))->not->toBeNull();
});

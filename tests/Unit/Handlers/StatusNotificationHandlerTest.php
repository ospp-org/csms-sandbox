<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\StatusNotificationHandler;
use App\Services\StationStateService;

test('StatusNotification updates bay status in Redis', function (): void {
    app(StationStateService::class)->resetState('stn_sn01', 2);

    $handler = app(StatusNotificationHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_sn01',
        action: 'StatusNotification',
        messageId: 'msg_sn_001',
        messageType: 'Event',
        payload: [
            'bayId' => 'bay_00000001',
            'bayNumber' => 1,
            'status' => 'Available',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $handler->handle($context);

    $state = app(StationStateService::class);
    expect($state->getBayStatus('stn_sn01', 1))->toBe('Available');
});

test('StatusNotification returns acknowledged', function (): void {
    $handler = app(StatusNotificationHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_sn02',
        action: 'StatusNotification',
        messageId: 'msg_sn_002',
        messageType: 'Event',
        payload: [
            'bayId' => 'bay_00000001',
            'bayNumber' => 1,
            'status' => 'Occupied',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

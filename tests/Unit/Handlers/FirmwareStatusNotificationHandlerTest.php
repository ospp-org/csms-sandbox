<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\FirmwareStatusNotificationHandler;

test('acknowledges firmware downloading notification', function (): void {
    $handler = app(FirmwareStatusNotificationHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_fsn01',
        action: 'FirmwareStatusNotification',
        messageId: 'msg_fsn_001',
        messageType: 'Event',
        payload: [
            'status' => 'Downloading',
            'firmwareVersion' => '2.0.0',
            'progress' => 50,
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

test('acknowledges firmware installed notification', function (): void {
    $handler = app(FirmwareStatusNotificationHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_fsn02',
        action: 'FirmwareStatusNotification',
        messageId: 'msg_fsn_002',
        messageType: 'Event',
        payload: [
            'status' => 'Installed',
            'firmwareVersion' => '2.0.0',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

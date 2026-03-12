<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\SecurityEventHandler;

test('SecurityEvent returns acknowledged', function (): void {
    $handler = app(SecurityEventHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_se01',
        action: 'SecurityEvent',
        messageId: 'msg_se_001',
        messageType: 'Event',
        payload: [
            'eventType' => 'InvalidMessageType',
            'timestamp' => '2026-03-11T10:00:00Z',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

test('SecurityEvent handles TamperDetected', function (): void {
    $handler = app(SecurityEventHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_se02',
        action: 'SecurityEvent',
        messageId: 'msg_se_002',
        messageType: 'Event',
        payload: [
            'eventType' => 'TamperDetected',
            'timestamp' => '2026-03-11T10:05:00Z',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

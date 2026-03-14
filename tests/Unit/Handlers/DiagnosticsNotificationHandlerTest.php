<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\DiagnosticsNotificationHandler;

test('acknowledges diagnostics notification', function (): void {
    $handler = app(DiagnosticsNotificationHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_dn01',
        action: 'DiagnosticsNotification',
        messageId: 'msg_dn_001',
        messageType: 'Event',
        payload: [
            'status' => 'Collecting',
            'progress' => 25,
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

test('acknowledges completed diagnostics', function (): void {
    $handler = app(DiagnosticsNotificationHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_dn02',
        action: 'DiagnosticsNotification',
        messageId: 'msg_dn_002',
        messageType: 'Event',
        payload: [
            'status' => 'Uploaded',
            'fileName' => 'diag_20260314.tar.gz',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

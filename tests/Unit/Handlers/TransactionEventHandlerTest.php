<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\TransactionEventHandler;

test('accepts offline transaction', function (): void {
    $handler = app(TransactionEventHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_txe01',
        action: 'TransactionEvent',
        messageId: 'msg_txe_001',
        messageType: 'Request',
        payload: [
            'offlineTxId' => 'otx_0a1b2c3d',
            'offlinePassId' => 'opass_0a1b2c3d',
            'userId' => 'usr_00000001',
            'bayId' => 'bay_00000001',
            'serviceId' => 'svc_wash',
            'startedAt' => '2026-03-14T10:00:00.000Z',
            'endedAt' => '2026-03-14T10:30:00.000Z',
            'durationSeconds' => 1800,
            'creditsCharged' => 100,
            'receipt' => ['hash' => 'abc123'],
            'txCounter' => 1,
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe(['status' => 'Accepted']);
});

test('returns response payload for publishing', function (): void {
    $handler = app(TransactionEventHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_txe02',
        action: 'TransactionEvent',
        messageId: 'msg_txe_002',
        messageType: 'Request',
        payload: [
            'offlineTxId' => 'otx_11223344',
            'offlinePassId' => 'opass_11223344',
            'userId' => 'usr_00000002',
            'bayId' => 'bay_00000002',
            'serviceId' => 'svc_dry',
            'startedAt' => '2026-03-14T11:00:00.000Z',
            'endedAt' => '2026-03-14T11:15:00.000Z',
            'durationSeconds' => 900,
            'creditsCharged' => 50,
            'receipt' => ['hash' => 'def456'],
            'txCounter' => 2,
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->responsePayload)->not->toBe([]);
});

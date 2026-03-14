<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\AuthorizeOfflinePassHandler;

test('returns Accepted with session parameters', function (): void {
    $handler = app(AuthorizeOfflinePassHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_aop01',
        action: 'AuthorizeOfflinePass',
        messageId: 'msg_aop_001',
        messageType: 'Request',
        payload: [
            'offlinePassId' => 'opass_0a1b2c3d',
            'offlinePass' => ['userId' => 'usr_00000001', 'revocationEpoch' => 1000, 'offlineAllowance' => 500],
            'deviceId' => 'dev_00000001',
            'counter' => 1,
            'bayId' => 'bay_00000001',
            'serviceId' => 'svc_wash',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload['status'])->toBe('Accepted');
    expect($result->responsePayload)->toHaveKeys(['sessionId', 'durationSeconds', 'creditsAuthorized']);
    expect($result->responsePayload['sessionId'])->toStartWith('sess_');
    expect($result->responsePayload['durationSeconds'])->toBe(3600);
    expect($result->responsePayload['creditsAuthorized'])->toBe(0);
});

test('generates unique session IDs', function (): void {
    $handler = app(AuthorizeOfflinePassHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_aop02',
        action: 'AuthorizeOfflinePass',
        messageId: 'msg_aop_002',
        messageType: 'Request',
        payload: [
            'offlinePassId' => 'opass_0a1b2c3d',
            'offlinePass' => ['userId' => 'usr_00000001', 'revocationEpoch' => 1000, 'offlineAllowance' => 500],
            'deviceId' => 'dev_00000001',
            'counter' => 2,
            'bayId' => 'bay_00000001',
            'serviceId' => 'svc_wash',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result1 = $handler->handle($context);
    $result2 = $handler->handle($context);

    expect($result1->responsePayload['sessionId'])->not->toBe($result2->responsePayload['sessionId']);
});

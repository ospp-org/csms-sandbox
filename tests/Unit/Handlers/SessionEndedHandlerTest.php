<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\SessionEndedHandler;
use App\Services\StationStateService;

test('SessionEnded TimerExpired sets bay to Finishing and clears session', function (): void {
    $state = app(StationStateService::class);
    $state->resetState('stn_se000001', 2);
    $state->setBayIdMapping('stn_se000001', 'bay_00000001', 1);
    $state->setBayStatus('stn_se000001', 1, 'Occupied');
    $state->setBaySession('stn_se000001', 1, 'sess_00000001', 'svc_wash');

    $handler = app(SessionEndedHandler::class);
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_se000001',
        action: 'SessionEnded',
        messageId: 'msg_se_001',
        messageType: 'Event',
        payload: [
            'sessionId' => 'sess_00000001',
            'bayId' => 'bay_00000001',
            'reason' => 'TimerExpired',
            'actualDurationSeconds' => 300,
            'creditsCharged' => 100,
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
    expect($state->getBayStatus('stn_se000001', 1))->toBe('Finishing');
    expect($state->getBaySession('stn_se000001', 1))->toBeNull();
});

test('SessionEnded Fault sets bay to Faulted and clears session', function (): void {
    $state = app(StationStateService::class);
    $state->resetState('stn_se000002', 2);
    $state->setBayIdMapping('stn_se000002', 'bay_00000001', 1);
    $state->setBayStatus('stn_se000002', 1, 'Occupied');
    $state->setBaySession('stn_se000002', 1, 'sess_00000002', 'svc_wash');

    $handler = app(SessionEndedHandler::class);
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_se000002',
        action: 'SessionEnded',
        messageId: 'msg_se_002',
        messageType: 'Event',
        payload: [
            'sessionId' => 'sess_00000002',
            'bayId' => 'bay_00000001',
            'reason' => 'Fault',
            'actualDurationSeconds' => 127,
            'creditsCharged' => 42,
            'meterValues' => ['liquidMl' => 18900, 'energyWh' => 63],
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
    expect($state->getBayStatus('stn_se000002', 1))->toBe('Faulted');
    expect($state->getBaySession('stn_se000002', 1))->toBeNull();
});

test('SessionEnded returns acknowledged with no response payload', function (): void {
    $handler = app(SessionEndedHandler::class);
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_se000003',
        action: 'SessionEnded',
        messageId: 'msg_se_003',
        messageType: 'Event',
        payload: [
            'sessionId' => 'sess_00000003',
            'bayId' => 'bay_00000001',
            'reason' => 'TimerExpired',
            'actualDurationSeconds' => 300,
            'creditsCharged' => 50,
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

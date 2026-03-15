<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Conformance\Rules\SessionStateRule;
use App\Services\StationStateService;

test('SessionStateRule passes for non-session actions', function (): void {
    $rule = new SessionStateRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ss000001',
        action: 'BootNotification',
        messageId: 'msg_ss_001',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
});

test('SessionStateRule fails when MeterValues sent without session', function (): void {
    $rule = new SessionStateRule();
    $state = app(StationStateService::class);
    $state->resetState('stn_ss000002', 2);
    $state->setBayIdMapping('stn_ss000002', 'bay_00000001', 1);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ss000002',
        action: 'MeterValues',
        messageId: 'msg_ss_002',
        messageType: 'Event',
        payload: ['bayId' => 'bay_00000001', 'sessionId' => 'sess_00000001', 'timestamp' => '2026-03-14T10:00:00.000Z', 'values' => []],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('no active session');
});

test('SessionStateRule passes when session exists', function (): void {
    $rule = new SessionStateRule();
    $state = app(StationStateService::class);
    $state->resetState('stn_ss000003', 2);
    $state->setBayIdMapping('stn_ss000003', 'bay_00000001', 1);
    $state->setBaySession('stn_ss000003', 1, 'sess_001');

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ss000003',
        action: 'MeterValues',
        messageId: 'msg_ss_003',
        messageType: 'Event',
        payload: ['bayId' => 'bay_00000001', 'sessionId' => 'sess_001', 'timestamp' => '2026-03-14T10:00:00.000Z', 'values' => []],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeTrue();
});

test('SessionStateRule passes for StopServiceResponse', function (): void {
    $rule = new SessionStateRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ss000004',
        action: 'StopService',
        messageId: 'msg_ss_004',
        messageType: 'Response',
        payload: ['status' => 'Accepted', 'actualDurationSeconds' => 300, 'creditsCharged' => 100],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
});

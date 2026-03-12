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

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ss000002',
        action: 'MeterValues',
        messageId: 'msg_ss_002',
        messageType: 'Event',
        payload: ['bayNumber' => 1],
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
    $state->setBaySession('stn_ss000003', 1, 'sess_001');

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ss000003',
        action: 'MeterValues',
        messageId: 'msg_ss_003',
        messageType: 'Event',
        payload: ['bayNumber' => 1],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeTrue();
});

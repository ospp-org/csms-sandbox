<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Conformance\Rules\BayTransitionRule;
use App\Services\StationStateService;

test('BayTransitionRule passes for non-StatusNotification', function (): void {
    $rule = new BayTransitionRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_bt000001',
        action: 'Heartbeat',
        messageId: 'msg_bt_001',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
});

test('BayTransitionRule passes for valid transition Unknown to Available', function (): void {
    $rule = new BayTransitionRule();
    $state = app(StationStateService::class);
    $state->resetState('stn_bt000002', 2);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_bt000002',
        action: 'StatusNotification',
        messageId: 'msg_bt_002',
        messageType: 'Event',
        payload: ['bayNumber' => 1, 'status' => 'Available'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeTrue();
});

test('BayTransitionRule fails for invalid transition Unknown to Occupied', function (): void {
    $rule = new BayTransitionRule();
    $state = app(StationStateService::class);
    $state->resetState('stn_bt000003', 2);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_bt000003',
        action: 'StatusNotification',
        messageId: 'msg_bt_003',
        messageType: 'Event',
        payload: ['bayNumber' => 1, 'status' => 'Occupied'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('Invalid transition: Unknown -> Occupied');
});

test('BayTransitionRule passes for self-transition Available to Available', function (): void {
    $rule = new BayTransitionRule();
    $state = app(StationStateService::class);
    $state->resetState('stn_bt000005', 2);
    $state->setBayStatus('stn_bt000005', 1, 'Available');

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_bt000005',
        action: 'StatusNotification',
        messageId: 'msg_bt_005',
        messageType: 'Event',
        payload: ['bayNumber' => 1, 'status' => 'Available'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeTrue();
});

test('BayTransitionRule passes for valid transition Available to Occupied', function (): void {
    $rule = new BayTransitionRule();
    $state = app(StationStateService::class);
    $state->resetState('stn_bt000004', 2);
    $state->setBayStatus('stn_bt000004', 1, 'Available');

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_bt000004',
        action: 'StatusNotification',
        messageId: 'msg_bt_004',
        messageType: 'Event',
        payload: ['bayNumber' => 1, 'status' => 'Occupied'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeTrue();
});

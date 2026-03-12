<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Conformance\Rules\HeartbeatTimingRule;
use App\Services\StationStateService;

test('HeartbeatTimingRule passes for non-heartbeat actions', function (): void {
    $rule = new HeartbeatTimingRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ht000001',
        action: 'BootNotification',
        messageId: 'msg_ht_001',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
});

test('HeartbeatTimingRule passes for first heartbeat', function (): void {
    $rule = new HeartbeatTimingRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ht000002',
        action: 'Heartbeat',
        messageId: 'msg_ht_002',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
});

test('HeartbeatTimingRule passes when heartbeat is within tolerance', function (): void {
    $rule = new HeartbeatTimingRule();
    $state = app(StationStateService::class);

    $state->setHeartbeatInterval('stn_ht000003', 30);
    $state->setLastHeartbeat('stn_ht000003', time() - 30);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ht000003',
        action: 'Heartbeat',
        messageId: 'msg_ht_003',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeTrue();
});

test('HeartbeatTimingRule fails when heartbeat is too early', function (): void {
    $rule = new HeartbeatTimingRule();
    $state = app(StationStateService::class);

    $state->setHeartbeatInterval('stn_ht000004', 30);
    $state->setLastHeartbeat('stn_ht000004', time() - 5);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ht000004',
        action: 'Heartbeat',
        messageId: 'msg_ht_004',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('expected 30s');
});

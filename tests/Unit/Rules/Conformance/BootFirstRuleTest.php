<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Conformance\Rules\BootFirstRule;
use App\Services\StationStateService;

test('BootFirstRule passes for BootNotification action', function (): void {
    $rule = new BootFirstRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_bf000001',
        action: 'BootNotification',
        messageId: 'msg_bf_001',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
    expect($result->rule)->toBe('boot_first');
});

test('BootFirstRule fails when non-boot message sent before boot', function (): void {
    $rule = new BootFirstRule();
    $state = app(StationStateService::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_bf000002',
        action: 'Heartbeat',
        messageId: 'msg_bf_002',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    // Station lifecycle is 'offline' by default (no state set)
    $result = $rule->check($context, $state);

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('before BootNotification');
});

test('BootFirstRule passes when station is online', function (): void {
    $rule = new BootFirstRule();
    $state = app(StationStateService::class);
    $state->setLifecycle('stn_bf000003', 'online');

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_bf000003',
        action: 'Heartbeat',
        messageId: 'msg_bf_003',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeTrue();
});

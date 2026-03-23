<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Conformance\Rules\OfflineTransactionRule;
use App\Services\StationStateService;

test('OfflineTransactionRule passes for valid sequential txCounter', function (): void {
    $rule = new OfflineTransactionRule();
    $state = app(StationStateService::class);

    // First transaction: counter 1
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_tx000001',
        action: 'TransactionEvent',
        messageId: 'msg_tx_001',
        messageType: 'Request',
        payload: ['txCounter' => 1],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, $state);
    expect($result->passed)->toBeTrue();

    // Second transaction: counter 2
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_tx000001',
        action: 'TransactionEvent',
        messageId: 'msg_tx_002',
        messageType: 'Request',
        payload: ['txCounter' => 2],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, $state);
    expect($result->passed)->toBeTrue();
});

test('OfflineTransactionRule fails for duplicate txCounter', function (): void {
    $rule = new OfflineTransactionRule();
    $state = app(StationStateService::class);

    $state->setLastTxCounter('stn_tx000002', 5);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_tx000002',
        action: 'TransactionEvent',
        messageId: 'msg_tx_003',
        messageType: 'Request',
        payload: ['txCounter' => 5],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('Duplicate or out-of-order txCounter');
});

test('OfflineTransactionRule fails for gap in txCounter', function (): void {
    $rule = new OfflineTransactionRule();
    $state = app(StationStateService::class);

    $state->setLastTxCounter('stn_tx000003', 3);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_tx000003',
        action: 'TransactionEvent',
        messageId: 'msg_tx_004',
        messageType: 'Request',
        payload: ['txCounter' => 7],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('Gap in txCounter sequence');
});

<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Conformance\Rules\EnvelopeFormatRule;
use App\Services\StationStateService;

test('EnvelopeFormatRule passes with valid envelope', function (): void {
    $rule = new EnvelopeFormatRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ef000001',
        action: 'BootNotification',
        messageId: 'msg_ef_001',
        messageType: 'Request',
        payload: [],
        envelope: [
            'action' => 'BootNotification',
            'messageId' => 'msg_ef_001',
            'messageType' => 'Request',
            'source' => 'Station',
            'protocolVersion' => '0.1.0',
            'timestamp' => '2026-03-09T10:00:05.000Z',
            'payload' => [],
        ],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
});

test('EnvelopeFormatRule fails with missing fields', function (): void {
    $rule = new EnvelopeFormatRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ef000002',
        action: 'BootNotification',
        messageId: 'msg_ef_002',
        messageType: 'Request',
        payload: [],
        envelope: [
            'action' => 'BootNotification',
            'payload' => [],
        ],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('Missing required field');
});

test('EnvelopeFormatRule fails with invalid timestamp format', function (): void {
    $rule = new EnvelopeFormatRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ef000003',
        action: 'BootNotification',
        messageId: 'msg_ef_003',
        messageType: 'Request',
        payload: [],
        envelope: [
            'action' => 'BootNotification',
            'messageId' => 'msg_ef_003',
            'messageType' => 'Request',
            'source' => 'Station',
            'protocolVersion' => '0.1.0',
            'timestamp' => '2026-03-09 10:00:05',
            'payload' => [],
        ],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('Invalid timestamp format');
});

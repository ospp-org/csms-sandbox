<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Conformance\Rules\MeterValuesFrequencyRule;
use App\Services\StationStateService;

test('MeterValuesFrequencyRule passes for valid meter values with advancing timestamp', function (): void {
    $rule = new MeterValuesFrequencyRule();
    $state = app(StationStateService::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_mv000001',
        action: 'MeterValues',
        messageId: 'msg_mv_001',
        messageType: 'Event',
        payload: [
            'bayId' => 'bay-1',
            'timestamp' => '2026-03-16T10:00:00Z',
            'values' => [
                ['value' => 100.5],
                ['value' => 0],
                ['value' => 42.3],
            ],
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, $state);
    expect($result->passed)->toBeTrue();

    // Second reading with later timestamp
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_mv000001',
        action: 'MeterValues',
        messageId: 'msg_mv_002',
        messageType: 'Event',
        payload: [
            'bayId' => 'bay-1',
            'timestamp' => '2026-03-16T10:05:00Z',
            'values' => [
                ['value' => 150.0],
            ],
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, $state);
    expect($result->passed)->toBeTrue();
});

test('MeterValuesFrequencyRule fails when timestamp goes backwards', function (): void {
    $rule = new MeterValuesFrequencyRule();
    $state = app(StationStateService::class);

    // Set a previous timestamp
    $state->setLastMeterTimestamp('stn_mv000002', 'bay-1', strtotime('2026-03-16T10:10:00Z'));

    // Send meter values with earlier timestamp
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_mv000002',
        action: 'MeterValues',
        messageId: 'msg_mv_003',
        messageType: 'Event',
        payload: [
            'bayId' => 'bay-1',
            'timestamp' => '2026-03-16T10:05:00Z',
            'values' => [
                ['value' => 50.0],
            ],
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('Meter values timestamp is not after previous');
});

<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Conformance\Rules\ResponseTimingRule;
use App\Services\StationStateService;

test('ResponseTimingRule passes for non-response actions', function (): void {
    $rule = new ResponseTimingRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_rt000001',
        action: 'Heartbeat',
        messageId: 'msg_rt_001',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
});

test('ResponseTimingRule passes when response is within timeout', function (): void {
    $tenant = Tenant::factory()->create();

    CommandHistory::factory()->for($tenant)->create([
        'station_id' => 'stn_rt000002',
        'action' => 'Reset',
        'status' => 'sent',
        'created_at' => now()->subSeconds(5),
    ]);

    $rule = new ResponseTimingRule();

    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_rt000002',
        action: 'Reset',
        messageId: 'msg_rt_002',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
});

test('ResponseTimingRule fails when response exceeds timeout', function (): void {
    $tenant = Tenant::factory()->create();

    CommandHistory::factory()->for($tenant)->create([
        'station_id' => 'stn_rt000003',
        'action' => 'Reset',
        'status' => 'sent',
        'created_at' => now()->subSeconds(45),
    ]);

    $rule = new ResponseTimingRule();

    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_rt000003',
        action: 'Reset',
        messageId: 'msg_rt_003',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('limit is 30s');
});

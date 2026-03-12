<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Models\MessageLog;
use App\Models\Tenant;
use App\Conformance\Rules\IdempotencyRule;
use App\Services\StationStateService;

test('IdempotencyRule passes for unique messageId', function (): void {
    $rule = new IdempotencyRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_id000001',
        action: 'Heartbeat',
        messageId: 'msg_unique_001',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
});

test('IdempotencyRule fails for duplicate messageId', function (): void {
    $tenant = Tenant::factory()->create();

    // Two inbound messages with the same messageId = duplicate
    MessageLog::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_id000002',
        'direction' => 'inbound',
        'action' => 'Heartbeat',
        'message_id' => 'msg_dup_001',
        'message_type' => 'Request',
        'payload' => [],
        'schema_valid' => true,
        'created_at' => now()->subSecond(),
    ]);
    MessageLog::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_id000002',
        'direction' => 'inbound',
        'action' => 'Heartbeat',
        'message_id' => 'msg_dup_001',
        'message_type' => 'Request',
        'payload' => [],
        'schema_valid' => true,
        'created_at' => now(),
    ]);

    $rule = new IdempotencyRule();

    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_id000002',
        action: 'Heartbeat',
        messageId: 'msg_dup_001',
        messageType: 'Request',
        payload: [],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('Duplicate messageId');
});

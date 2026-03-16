<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\ResetResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('Accepted sets lifecycle to resetting', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_reset01']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_reset01',
        'action' => 'Reset',
        'message_id' => 'msg_reset_001',
        'payload' => ['type' => 'Soft'],
        'status' => 'sent',
    ]);

    app(StationStateService::class)->resetState('stn_reset01', 2);

    $handler = app(ResetResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_reset01',
        action: 'Reset',
        messageId: 'msg_reset_001',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();

    $stationState = app(StationStateService::class);
    expect($stationState->getLifecycle('stn_reset01'))->toBe('resetting');
});

test('Rejected keeps lifecycle unchanged', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_reset02']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_reset02',
        'action' => 'Reset',
        'message_id' => 'msg_reset_002',
        'payload' => ['type' => 'Hard'],
        'status' => 'sent',
    ]);

    app(StationStateService::class)->resetState('stn_reset02', 2);

    $handler = app(ResetResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_reset02',
        action: 'Reset',
        messageId: 'msg_reset_002',
        messageType: 'Response',
        payload: ['status' => 'Rejected'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $handler->handle($context);

    $stationState = app(StationStateService::class);
    expect($stationState->getLifecycle('stn_reset02'))->toBe('online');
});

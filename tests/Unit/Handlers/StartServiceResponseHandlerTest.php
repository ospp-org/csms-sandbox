<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\StartServiceResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('Accepted sets bay to Occupied and records session', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_start01']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_start01',
        'action' => 'StartService',
        'message_id' => 'msg_start_001',
        'payload' => ['bayId' => 'bay_00000001', 'serviceId' => 'svc_wash'],
        'status' => 'sent',
    ]);

    app(StationStateService::class)->resetState('stn_start01', 2);

    $handler = app(StartServiceResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_start01',
        action: 'StartServiceResponse',
        messageId: 'msg_start_001',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();

    $stationState = app(StationStateService::class);
    expect($stationState->getBayStatus('stn_start01', 1))->toBe('Occupied');

    $command->refresh();
    expect($command->status)->toBe('responded');
});

test('Rejected does not change bay state', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_start02']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_start02',
        'action' => 'StartService',
        'message_id' => 'msg_start_002',
        'payload' => ['bayId' => 'bay_00000001', 'serviceId' => 'svc_wash'],
        'status' => 'sent',
    ]);

    app(StationStateService::class)->resetState('stn_start02', 2);

    $handler = app(StartServiceResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_start02',
        action: 'StartServiceResponse',
        messageId: 'msg_start_002',
        messageType: 'Response',
        payload: ['status' => 'Rejected'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $handler->handle($context);

    $stationState = app(StationStateService::class);
    expect($stationState->getBayStatus('stn_start02', 1))->toBe('Unknown');
});

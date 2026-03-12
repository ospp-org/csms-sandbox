<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\StopServiceResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('Accepted sets bay to Finishing', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_stop01']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_stop01',
        'action' => 'StopService',
        'message_id' => 'msg_stop_001',
        'payload' => ['bayId' => 'bay_00000001'],
        'status' => 'sent',
    ]);

    app(StationStateService::class)->resetState('stn_stop01', 2);

    $handler = app(StopServiceResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_stop01',
        action: 'StopServiceResponse',
        messageId: 'msg_stop_001',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();

    $stationState = app(StationStateService::class);
    expect($stationState->getBayStatus('stn_stop01', 1))->toBe('Finishing');
});

test('updates command to responded', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_stop02']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_stop02',
        'action' => 'StopService',
        'message_id' => 'msg_stop_002',
        'payload' => ['bayId' => 'bay_00000001'],
        'status' => 'sent',
    ]);

    app(StationStateService::class)->resetState('stn_stop02', 2);

    $handler = app(StopServiceResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_stop02',
        action: 'StopServiceResponse',
        messageId: 'msg_stop_002',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $handler->handle($context);

    $command->refresh();
    expect($command->status)->toBe('responded');
});

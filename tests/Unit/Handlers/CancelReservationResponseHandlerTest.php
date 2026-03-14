<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\CancelReservationResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('Accepted sets bay to Available', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_cancel01']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_cancel01',
        'action' => 'CancelReservation',
        'message_id' => 'msg_cancel_001',
        'payload' => ['bayId' => 'bay_00000001'],
        'status' => 'sent',
    ]);

    $stationState = app(StationStateService::class);
    $stationState->resetState('stn_cancel01', 2);
    $stationState->setBayIdMapping('stn_cancel01', 'bay_00000001', 1);
    $stationState->setBayStatus('stn_cancel01', 1, 'Reserved');
    $stationState->setBayReservation('stn_cancel01', 1, 'res_to_cancel');

    $handler = app(CancelReservationResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_cancel01',
        action: 'CancelReservationResponse',
        messageId: 'msg_cancel_001',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($stationState->getBayStatus('stn_cancel01', 1))->toBe('Available');
});

test('clears reservation', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_cancel02']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_cancel02',
        'action' => 'CancelReservation',
        'message_id' => 'msg_cancel_002',
        'payload' => ['bayId' => 'bay_00000001'],
        'status' => 'sent',
    ]);

    $stationState = app(StationStateService::class);
    $stationState->resetState('stn_cancel02', 2);
    $stationState->setBayIdMapping('stn_cancel02', 'bay_00000001', 1);
    $stationState->setBayStatus('stn_cancel02', 1, 'Reserved');
    $stationState->setBayReservation('stn_cancel02', 1, 'res_to_clear');

    $handler = app(CancelReservationResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_cancel02',
        action: 'CancelReservationResponse',
        messageId: 'msg_cancel_002',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $handler->handle($context);

    expect($stationState->getBayReservation('stn_cancel02', 1))->toBeNull();
});

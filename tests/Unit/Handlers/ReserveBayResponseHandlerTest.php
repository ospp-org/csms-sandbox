<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\ReserveBayResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('Accepted sets bay to Reserved', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_reserve01']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_reserve01',
        'action' => 'ReserveBay',
        'message_id' => 'msg_reserve_001',
        'payload' => ['bayId' => 'bay_00000001', 'reservationId' => 'res_abc123'],
        'status' => 'sent',
    ]);

    app(StationStateService::class)->resetState('stn_reserve01', 2);
    app(StationStateService::class)->setBayIdMapping('stn_reserve01', 'bay_00000001', 1);

    $handler = app(ReserveBayResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_reserve01',
        action: 'ReserveBayResponse',
        messageId: 'msg_reserve_001',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();

    $stationState = app(StationStateService::class);
    expect($stationState->getBayStatus('stn_reserve01', 1))->toBe('Reserved');
});

test('sets reservation in Redis', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_reserve02']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_reserve02',
        'action' => 'ReserveBay',
        'message_id' => 'msg_reserve_002',
        'payload' => ['bayId' => 'bay_00000001', 'reservationId' => 'res_xyz789'],
        'status' => 'sent',
    ]);

    app(StationStateService::class)->resetState('stn_reserve02', 2);
    app(StationStateService::class)->setBayIdMapping('stn_reserve02', 'bay_00000001', 1);

    $handler = app(ReserveBayResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_reserve02',
        action: 'ReserveBayResponse',
        messageId: 'msg_reserve_002',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $handler->handle($context);

    $stationState = app(StationStateService::class);
    expect($stationState->getBayReservation('stn_reserve02', 1))->toBe('res_xyz789');
});

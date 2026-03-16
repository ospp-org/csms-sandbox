<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\SetMaintenanceModeResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('Accepted with bayId sets bay to Unavailable', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_mm01']);

    app(StationStateService::class)->resetState('stn_mm01', 2);
    app(StationStateService::class)->setBayIdMapping('stn_mm01', 'bay_00000001', 1);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_mm01',
        'action' => 'SetMaintenanceMode',
        'message_id' => 'msg_mm001',
        'payload' => ['enabled' => true, 'bayId' => 'bay_00000001'],
        'status' => 'sent',
    ]);

    $handler = app(SetMaintenanceModeResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_mm01',
        action: 'SetMaintenanceMode',
        messageId: 'msg_mm001',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );
    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);

    $command->refresh();
    expect($command->status)->toBe('responded');

    $bayStatus = app(StationStateService::class)->getBayStatus('stn_mm01', 1);
    expect($bayStatus)->toBe('Unavailable');
});

test('Accepted with enabled=false sets bay Available', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_mm02']);

    app(StationStateService::class)->resetState('stn_mm02', 2);
    app(StationStateService::class)->setBayIdMapping('stn_mm02', 'bay_00000001', 1);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_mm02',
        'action' => 'SetMaintenanceMode',
        'message_id' => 'msg_mm002',
        'payload' => ['enabled' => false, 'bayId' => 'bay_00000001'],
        'status' => 'sent',
    ]);

    $handler = app(SetMaintenanceModeResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_mm02',
        action: 'SetMaintenanceMode',
        messageId: 'msg_mm002',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );
    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);

    $command->refresh();
    expect($command->status)->toBe('responded');

    $bayStatus = app(StationStateService::class)->getBayStatus('stn_mm02', 1);
    expect($bayStatus)->toBe('Available');
});

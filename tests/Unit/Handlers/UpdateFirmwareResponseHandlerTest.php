<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\UpdateFirmwareResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;

test('Accepted updates command to responded', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_ufr001']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_ufr001',
        'action' => 'UpdateFirmware',
        'message_id' => 'msg_ufr001',
        'payload' => ['firmwareUrl' => 'https://example.com/fw.bin'],
        'status' => 'sent',
    ]);

    $handler = app(UpdateFirmwareResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_ufr001',
        action: 'UpdateFirmwareResponse',
        messageId: 'msg_ufr001',
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
});

test('handles missing command gracefully', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_ufr002']);

    $handler = app(UpdateFirmwareResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_ufr002',
        action: 'UpdateFirmwareResponse',
        messageId: 'msg_ufr_nomatch',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );
    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

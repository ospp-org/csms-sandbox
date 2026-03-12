<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\TriggerMessageResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;

test('marks command as responded', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_tmr001']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_tmr001',
        'action' => 'TriggerMessage',
        'message_id' => 'msg_tmr001',
        'payload' => ['requestedMessage' => 'Heartbeat'],
        'status' => 'sent',
    ]);

    $handler = app(TriggerMessageResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_tmr001',
        action: 'TriggerMessageResponse',
        messageId: 'msg_tmr001',
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

test('handles NotImplemented status', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_tmr002']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_tmr002',
        'action' => 'TriggerMessage',
        'message_id' => 'msg_tmr002',
        'payload' => ['requestedMessage' => 'DiagnosticsStatusNotification'],
        'status' => 'sent',
    ]);

    $handler = app(TriggerMessageResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_tmr002',
        action: 'TriggerMessageResponse',
        messageId: 'msg_tmr002',
        messageType: 'Response',
        payload: ['status' => 'NotImplemented'],
        envelope: [],
        protocolVersion: '0.1.0',
    );
    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);

    $command->refresh();
    expect($command->status)->toBe('responded');
});

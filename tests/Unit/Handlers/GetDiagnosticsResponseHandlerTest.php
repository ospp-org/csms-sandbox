<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\GetDiagnosticsResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;

test('updates command status on response', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_udr001']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_udr001',
        'action' => 'GetDiagnostics',
        'message_id' => 'msg_udr001',
        'payload' => ['uploadUrl' => 'https://example.com/diag'],
        'status' => 'sent',
    ]);

    $handler = app(GetDiagnosticsResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_udr001',
        action: 'GetDiagnosticsResponse',
        messageId: 'msg_udr001',
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

test('acknowledges without pending command', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_udr002']);

    $handler = app(GetDiagnosticsResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_udr002',
        action: 'GetDiagnosticsResponse',
        messageId: 'msg_udr_nomatch',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );
    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

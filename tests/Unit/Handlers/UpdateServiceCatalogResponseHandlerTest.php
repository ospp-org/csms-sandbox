<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\UpdateServiceCatalogResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;

test('updates command to responded', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_uscr001']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_uscr001',
        'action' => 'UpdateServiceCatalog',
        'message_id' => 'msg_uscr001',
        'payload' => ['catalogUrl' => 'https://example.com/catalog.json'],
        'status' => 'sent',
    ]);

    $handler = app(UpdateServiceCatalogResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_uscr001',
        action: 'UpdateServiceCatalogResponse',
        messageId: 'msg_uscr001',
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

test('acknowledges when no command found', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_uscr002']);

    $handler = app(UpdateServiceCatalogResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_uscr002',
        action: 'UpdateServiceCatalogResponse',
        messageId: 'msg_uscr_nomatch',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );
    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

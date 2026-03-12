<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\ChangeConfigurationResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('Accepted updates config in Redis', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_config01']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_config01',
        'action' => 'ChangeConfiguration',
        'message_id' => 'msg_config_001',
        'payload' => ['keys' => ['heartbeatInterval' => '60']],
        'status' => 'sent',
    ]);

    $handler = app(ChangeConfigurationResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_config01',
        action: 'ChangeConfigurationResponse',
        messageId: 'msg_config_001',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();

    $stationState = app(StationStateService::class);
    $config = $stationState->getConfig('stn_config01');
    expect($config)->toHaveKey('heartbeatInterval');
    expect($config['heartbeatInterval'])->toBe('60');
});

test('Rejected does not update config', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_config02']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_config02',
        'action' => 'ChangeConfiguration',
        'message_id' => 'msg_config_002',
        'payload' => ['keys' => ['heartbeatInterval' => '120']],
        'status' => 'sent',
    ]);

    $handler = app(ChangeConfigurationResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_config02',
        action: 'ChangeConfigurationResponse',
        messageId: 'msg_config_002',
        messageType: 'Response',
        payload: ['status' => 'Rejected'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $handler->handle($context);

    $stationState = app(StationStateService::class);
    $config = $stationState->getConfig('stn_config02');
    expect($config)->not->toHaveKey('heartbeatInterval');
});

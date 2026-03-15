<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\ChangeConfigurationResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('All Accepted results apply config in Redis', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_config01']);

    CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_config01',
        'action' => 'ChangeConfiguration',
        'message_id' => 'msg_config_001',
        'payload' => ['keys' => [
            ['key' => 'heartbeatInterval', 'value' => '60'],
            ['key' => 'retryTimeout', 'value' => '30'],
        ]],
        'status' => 'sent',
    ]);

    $handler = app(ChangeConfigurationResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_config01',
        action: 'ChangeConfiguration',
        messageId: 'msg_config_001',
        messageType: 'Response',
        payload: ['results' => [
            ['key' => 'heartbeatInterval', 'status' => 'Accepted'],
            ['key' => 'retryTimeout', 'status' => 'RebootRequired'],
        ]],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();

    $stationState = app(StationStateService::class);
    $config = $stationState->getConfig('stn_config01');
    expect($config['heartbeatInterval'])->toBe('60');
    expect($config['retryTimeout'])->toBe('30');
});

test('Any Rejected result prevents all config changes (atomic)', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_config02']);

    CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_config02',
        'action' => 'ChangeConfiguration',
        'message_id' => 'msg_config_002',
        'payload' => ['keys' => [
            ['key' => 'heartbeatInterval', 'value' => '120'],
            ['key' => 'debugMode', 'value' => 'true'],
        ]],
        'status' => 'sent',
    ]);

    $handler = app(ChangeConfigurationResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_config02',
        action: 'ChangeConfiguration',
        messageId: 'msg_config_002',
        messageType: 'Response',
        payload: ['results' => [
            ['key' => 'heartbeatInterval', 'status' => 'Accepted'],
            ['key' => 'debugMode', 'status' => 'Rejected', 'errorCode' => 1001, 'errorText' => 'ReadOnly'],
        ]],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $handler->handle($context);

    $stationState = app(StationStateService::class);
    $config = $stationState->getConfig('stn_config02');
    expect($config)->not->toHaveKey('heartbeatInterval');
    expect($config)->not->toHaveKey('debugMode');
});

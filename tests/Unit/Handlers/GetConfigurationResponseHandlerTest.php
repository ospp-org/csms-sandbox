<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\GetConfigurationResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('Stores configuration in Redis', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_getconf01']);

    CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_getconf01',
        'action' => 'GetConfiguration',
        'message_id' => 'msg_getconf_001',
        'payload' => [],
        'status' => 'sent',
    ]);

    $handler = app(GetConfigurationResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_getconf01',
        action: 'GetConfiguration',
        messageId: 'msg_getconf_001',
        messageType: 'Response',
        payload: ['configuration' => [
            ['key' => 'heartbeatInterval', 'value' => '30', 'readonly' => false],
            ['key' => 'firmwareVersion', 'value' => '1.2.0', 'readonly' => true],
        ]],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();

    $stationState = app(StationStateService::class);
    $config = $stationState->getConfig('stn_getconf01');
    expect($config['heartbeatInterval'])->toBe('30');
    expect($config['firmwareVersion'])->toBe('1.2.0');
});

test('Works without pending command', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_getconf02']);

    $handler = app(GetConfigurationResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_getconf02',
        action: 'GetConfiguration',
        messageId: 'msg_getconf_no_cmd',
        messageType: 'Response',
        payload: ['configuration' => [
            ['key' => 'key2', 'value' => 'val2', 'readonly' => false],
        ]],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();

    $stationState = app(StationStateService::class);
    $config = $stationState->getConfig('stn_getconf02');
    expect($config['key2'])->toBe('val2');
});

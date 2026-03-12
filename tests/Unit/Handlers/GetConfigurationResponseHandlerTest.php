<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\GetConfigurationResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\StationStateService;

test('Accepted stores configuration in Redis', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_getconf01']);

    $command = CommandHistory::create([
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
        action: 'GetConfigurationResponse',
        messageId: 'msg_getconf_001',
        messageType: 'Response',
        payload: ['status' => 'Accepted', 'configuration' => ['key1' => 'val1']],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();

    $stationState = app(StationStateService::class);
    $config = $stationState->getConfig('stn_getconf01');
    expect($config)->toHaveKey('key1');
    expect($config['key1'])->toBe('val1');
});

test('works without pending command', function (): void {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_getconf02']);

    $handler = app(GetConfigurationResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_getconf02',
        action: 'GetConfigurationResponse',
        messageId: 'msg_getconf_no_cmd',
        messageType: 'Response',
        payload: ['status' => 'Accepted', 'configuration' => ['key2' => 'val2']],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
});

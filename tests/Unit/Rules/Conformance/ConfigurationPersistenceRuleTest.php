<?php

declare(strict_types=1);

use App\Conformance\Rules\ConfigurationPersistenceRule;
use App\Dto\HandlerContext;
use App\Services\StationStateService;

test('ConfigurationPersistenceRule passes when all results are valid', function (): void {
    $rule = new ConfigurationPersistenceRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_cp000001',
        action: 'ChangeConfiguration',
        messageId: 'msg_cp_001',
        messageType: 'Response',
        payload: [
            'results' => [
                ['key' => 'HeartbeatInterval', 'status' => 'Accepted'],
                ['key' => 'MeterValueSampleInterval', 'status' => 'RebootRequired'],
            ],
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
});

test('ConfigurationPersistenceRule fails when Rejected result missing errorCode', function (): void {
    $rule = new ConfigurationPersistenceRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_cp000002',
        action: 'ChangeConfiguration',
        messageId: 'msg_cp_002',
        messageType: 'Response',
        payload: [
            'results' => [
                ['key' => 'UnknownKey', 'status' => 'Rejected'],
            ],
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain("Key 'UnknownKey' status 'Rejected' missing errorCode/errorText");
});

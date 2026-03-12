<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\MeterValuesHandler;

test('MeterValues returns acknowledged', function (): void {
    $handler = app(MeterValuesHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_mv01',
        action: 'MeterValues',
        messageId: 'msg_mv_001',
        messageType: 'Request',
        payload: [
            'bayId' => 'bay_00000001',
            'sessionId' => 'sess_abc123',
            'readings' => [
                ['measurand' => 'Energy.Active.Import.Register', 'value' => '1500', 'unit' => 'Wh'],
                ['measurand' => 'Power.Active.Import', 'value' => '3000', 'unit' => 'W'],
            ],
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

test('MeterValues handles empty readings', function (): void {
    $handler = app(MeterValuesHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_mv02',
        action: 'MeterValues',
        messageId: 'msg_mv_002',
        messageType: 'Request',
        payload: [
            'bayId' => 'bay_00000001',
            'sessionId' => 'sess_xyz456',
            'readings' => [],
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

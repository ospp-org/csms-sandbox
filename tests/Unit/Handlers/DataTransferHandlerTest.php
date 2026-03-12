<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\DataTransferHandler;

test('DataTransfer returns Accepted', function (): void {
    $handler = app(DataTransferHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_dt01',
        action: 'DataTransfer',
        messageId: 'msg_dt_001',
        messageType: 'Request',
        payload: [
            'vendorId' => 'com.example.vendor',
            'dataId' => 'diagnosticInfo',
            'data' => '{"key":"value"}',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload['status'])->toBe('Accepted');
    expect($result->responsePayload['data'])->toBeNull();
});

test('DataTransfer accepts any vendor data', function (): void {
    $handler = app(DataTransferHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_dt02',
        action: 'DataTransfer',
        messageId: 'msg_dt_002',
        messageType: 'Request',
        payload: [
            'vendorId' => 'org.other.vendor',
            'dataId' => 'customReport',
            'data' => 'arbitrary-payload',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload['status'])->toBe('Accepted');
});

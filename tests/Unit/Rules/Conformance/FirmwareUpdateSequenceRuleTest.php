<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Conformance\Rules\FirmwareUpdateSequenceRule;
use App\Services\StationStateService;

test('FirmwareUpdateSequenceRule passes for valid firmware transition sequence', function (): void {
    $rule = new FirmwareUpdateSequenceRule();
    $state = app(StationStateService::class);

    // First notification: '' -> Downloading
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_fw000001',
        action: 'FirmwareStatusNotification',
        messageId: 'msg_fw_001',
        messageType: 'Event',
        payload: ['status' => 'Downloading'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);
    expect($result->passed)->toBeTrue();

    // Second: Downloading -> Downloaded
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_fw000001',
        action: 'FirmwareStatusNotification',
        messageId: 'msg_fw_002',
        messageType: 'Event',
        payload: ['status' => 'Downloaded'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);
    expect($result->passed)->toBeTrue();

    // Third: Downloaded -> Installing
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_fw000001',
        action: 'FirmwareStatusNotification',
        messageId: 'msg_fw_003',
        messageType: 'Event',
        payload: ['status' => 'Installing'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);
    expect($result->passed)->toBeTrue();

    // Fourth: Installing -> Installed
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_fw000001',
        action: 'FirmwareStatusNotification',
        messageId: 'msg_fw_004',
        messageType: 'Event',
        payload: ['status' => 'Installed'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);
    expect($result->passed)->toBeTrue();
});

test('FirmwareUpdateSequenceRule fails for invalid firmware transition', function (): void {
    $rule = new FirmwareUpdateSequenceRule();
    $state = app(StationStateService::class);

    // Set firmware status to Downloading
    $state->setFirmwareStatus('stn_fw000002', 'Downloading');

    // Attempt invalid: Downloading -> Installed (skipping Downloaded and Installing)
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_fw000002',
        action: 'FirmwareStatusNotification',
        messageId: 'msg_fw_005',
        messageType: 'Event',
        payload: ['status' => 'Installed'],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('Invalid firmware transition: Downloading -> Installed');
});

<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Conformance\Rules\DiagnosticsUploadRule;
use App\Services\StationStateService;

test('DiagnosticsUploadRule passes for valid diagnostics transition sequence', function (): void {
    $rule = new DiagnosticsUploadRule();
    $state = app(StationStateService::class);

    // First: '' -> Collecting
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_dg000001',
        action: 'DiagnosticsNotification',
        messageId: 'msg_dg_001',
        messageType: 'Event',
        payload: ['status' => 'Collecting'],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, $state);
    expect($result->passed)->toBeTrue();

    // Second: Collecting -> Uploading
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_dg000001',
        action: 'DiagnosticsNotification',
        messageId: 'msg_dg_002',
        messageType: 'Event',
        payload: ['status' => 'Uploading'],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, $state);
    expect($result->passed)->toBeTrue();

    // Third: Uploading -> Uploaded
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_dg000001',
        action: 'DiagnosticsNotification',
        messageId: 'msg_dg_003',
        messageType: 'Event',
        payload: ['status' => 'Uploaded'],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, $state);
    expect($result->passed)->toBeTrue();
});

test('DiagnosticsUploadRule fails for invalid diagnostics transition', function (): void {
    $rule = new DiagnosticsUploadRule();
    $state = app(StationStateService::class);

    // Set diagnostics status to Collecting
    $state->setDiagnosticsStatus('stn_dg000002', 'Collecting');

    // Attempt invalid: Collecting -> Uploaded (skipping Uploading)
    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_dg000002',
        action: 'DiagnosticsNotification',
        messageId: 'msg_dg_004',
        messageType: 'Event',
        payload: ['status' => 'Uploaded'],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, $state);

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('Invalid diagnostics transition: Collecting -> Uploaded');
});

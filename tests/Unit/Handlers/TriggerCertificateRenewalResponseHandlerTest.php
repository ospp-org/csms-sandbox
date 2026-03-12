<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\TriggerCertificateRenewalResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;

test('updates command to responded', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_tcrr001']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_tcrr001',
        'action' => 'TriggerCertificateRenewal',
        'message_id' => 'msg_tcrr001',
        'payload' => ['certificateType' => 'ChargingStationCertificate'],
        'status' => 'sent',
    ]);

    $handler = app(TriggerCertificateRenewalResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_tcrr001',
        action: 'TriggerCertificateRenewalResponse',
        messageId: 'msg_tcrr001',
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

test('works without pending command', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_tcrr002']);

    $handler = app(TriggerCertificateRenewalResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_tcrr002',
        action: 'TriggerCertificateRenewalResponse',
        messageId: 'msg_tcrr_nomatch',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.1.0',
    );
    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);
});

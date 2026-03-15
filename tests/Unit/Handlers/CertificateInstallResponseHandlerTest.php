<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\CertificateInstallResponseHandler;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Models\TenantStation;

test('updates command to responded on Accepted', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_cir001']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_cir001',
        'action' => 'CertificateInstall',
        'message_id' => 'msg_cir001',
        'payload' => ['certificate' => '-----BEGIN CERTIFICATE-----'],
        'status' => 'sent',
    ]);

    $handler = app(CertificateInstallResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_cir001',
        action: 'CertificateInstall',
        messageId: 'msg_cir001',
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

test('handles Rejected status', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_cir002']);

    $command = CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_cir002',
        'action' => 'CertificateInstall',
        'message_id' => 'msg_cir002',
        'payload' => ['certificate' => '-----BEGIN CERTIFICATE-----'],
        'status' => 'sent',
    ]);

    $handler = app(CertificateInstallResponseHandler::class);
    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_cir002',
        action: 'CertificateInstall',
        messageId: 'msg_cir002',
        messageType: 'Response',
        payload: ['status' => 'Rejected'],
        envelope: [],
        protocolVersion: '0.1.0',
    );
    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe([]);

    $command->refresh();
    expect($command->status)->toBe('responded');
});

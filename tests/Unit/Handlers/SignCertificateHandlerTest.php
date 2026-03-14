<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Handlers\SignCertificateHandler;

test('SignCertificate returns certificate', function (): void {
    $handler = app(SignCertificateHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_sc01',
        action: 'SignCertificate',
        messageId: 'msg_sc_001',
        messageType: 'Request',
        payload: [
            'csr' => "-----BEGIN CERTIFICATE REQUEST-----\nMIIBxTCCAWugAwIBAgI...\n-----END CERTIFICATE REQUEST-----",
            'certificateType' => 'StationCertificate',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload['status'])->toBe('Accepted');
    expect($result->responsePayload)->not->toHaveKey('certificate');
});

test('SignCertificate acknowledges CSR without certificate in response', function (): void {
    $handler = app(SignCertificateHandler::class);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_sc02',
        action: 'SignCertificate',
        messageId: 'msg_sc_002',
        messageType: 'Request',
        payload: [
            'csr' => "-----BEGIN CERTIFICATE REQUEST-----\nMIICvDCCAaQCAQAwdzET...\n-----END CERTIFICATE REQUEST-----",
            'certificateType' => 'MQTTClientCertificate',
        ],
        envelope: [],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload)->toBe(['status' => 'Accepted']);
});

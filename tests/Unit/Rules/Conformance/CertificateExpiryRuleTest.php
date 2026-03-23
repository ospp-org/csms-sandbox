<?php

declare(strict_types=1);

use App\Conformance\Rules\CertificateExpiryRule;
use App\Dto\HandlerContext;
use App\Services\StationStateService;

test('CertificateExpiryRule passes for valid PEM CSR with valid certificateType', function (): void {
    $rule = new CertificateExpiryRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ce000001',
        action: 'SignCertificate',
        messageId: 'msg_ce_001',
        messageType: 'Request',
        payload: [
            'csr' => '-----BEGIN CERTIFICATE REQUEST-----MIICYjCCAUo...',
            'certificateType' => 'StationCertificate',
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
});

test('CertificateExpiryRule fails for invalid CSR format', function (): void {
    $rule = new CertificateExpiryRule();

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_ce000002',
        action: 'SignCertificate',
        messageId: 'msg_ce_002',
        messageType: 'Request',
        payload: [
            'csr' => 'not-a-valid-pem-string',
            'certificateType' => 'StationCertificate',
        ],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toBe('CSR must be PEM-encoded PKCS#10');
});

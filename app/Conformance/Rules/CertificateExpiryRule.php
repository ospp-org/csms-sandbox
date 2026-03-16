<?php

declare(strict_types=1);

namespace App\Conformance\Rules;

use App\Contracts\ConformanceRule;
use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

final class CertificateExpiryRule implements ConformanceRule
{
    public function name(): string
    {
        return 'certificate_format';
    }

    public function check(HandlerContext $context, StationStateService $state): RuleResult
    {
        if ($context->action !== 'SignCertificate' || $context->messageType !== 'Request') {
            return new RuleResult(true, 'certificate_format');
        }

        $csr = $context->payload['csr'] ?? '';
        $type = $context->payload['certificateType'] ?? '';

        if (! is_string($csr) || ! str_starts_with($csr, '-----BEGIN CERTIFICATE REQUEST-----')) {
            return new RuleResult(false, 'certificate_format', 'CSR must be PEM-encoded PKCS#10');
        }

        if (! in_array($type, ['StationCertificate', 'MQTTClientCertificate'], true)) {
            return new RuleResult(false, 'certificate_format', "Invalid certificateType: {$type}");
        }

        return new RuleResult(true, 'certificate_format');
    }
}

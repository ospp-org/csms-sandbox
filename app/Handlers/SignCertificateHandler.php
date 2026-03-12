<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;

final class SignCertificateHandler implements OsppHandler
{
    public function handle(HandlerContext $context): HandlerResult
    {
        // Sandbox: return a placeholder self-signed certificate PEM.
        // A real CSMS would sign the CSR from $context->payload['csr'] here.

        return HandlerResult::accepted([
            'status' => 'Accepted',
            'certificate' => "-----BEGIN CERTIFICATE-----\nMIIBxTCCAWugAwIBAgI...sandbox-test-cert...\n-----END CERTIFICATE-----",
        ]);
    }
}

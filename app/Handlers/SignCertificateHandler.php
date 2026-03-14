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
        // Sandbox: acknowledge CSR receipt. A real CSMS would process the CSR
        // and deliver the signed certificate via a separate CertificateInstall command.

        return HandlerResult::accepted([
            'status' => 'Accepted',
        ]);
    }
}

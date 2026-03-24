<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\TenantStation;
use Illuminate\Support\Carbon;

interface CertificateServiceInterface
{
    public function isConfigured(): bool;

    /**
     * @return array{cert: string, key: string, expires_at: Carbon}
     */
    public function generateStationCert(TenantStation $station): array;

    public function revokeCert(TenantStation $station): void;

    public function getCaChain(): string;
}

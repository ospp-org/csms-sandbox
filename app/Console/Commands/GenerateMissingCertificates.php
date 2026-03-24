<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TenantStation;
use App\Contracts\CertificateServiceInterface;
use Illuminate\Console\Command;

final class GenerateMissingCertificates extends Command
{
    protected $signature = 'certificates:generate-missing';
    protected $description = 'Generate mTLS certificates for stations that do not have one';

    public function handle(CertificateServiceInterface $certificateService): int
    {
        if (! $certificateService->isConfigured()) {
            $count = TenantStation::whereNull('station_cert')->count();
            $this->warn("PKI not configured. Skipped {$count} station(s).");

            return self::FAILURE;
        }

        $stations = TenantStation::whereNull('station_cert')->get();

        if ($stations->isEmpty()) {
            $this->info('All stations already have certificates.');

            return self::SUCCESS;
        }

        $generated = 0;

        foreach ($stations as $station) {
            try {
                $certificateService->generateStationCert($station);
                $this->line("Generated cert for {$station->station_id}");
                $generated++;
            } catch (\Throwable $e) {
                $this->error("Failed for {$station->station_id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Generated: {$generated}, Total: {$stations->count()}");

        return self::SUCCESS;
    }
}

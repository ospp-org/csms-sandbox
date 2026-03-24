<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\CertificateServiceInterface;
use App\Models\Tenant;
use App\Models\TenantStation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class SimulatorStationsSeeder extends Seeder
{
    private const STATION_COUNT = 30;
    private const BAY_COUNT = 2;

    public function run(): void
    {
        $tenant = Tenant::where('email', 'dev@ospp-standard.org')->first();

        if ($tenant === null) {
            $this->command?->error('Dev tenant not found. Run DevelopmentSeeder first.');

            return;
        }

        $certService = app(CertificateServiceInterface::class);
        $pkiConfigured = $certService->isConfigured();
        $created = 0;
        $updated = 0;

        for ($i = 1; $i <= self::STATION_COUNT; $i++) {
            $stationId = 'stn_' . str_pad(dechex($i), 8, '0', STR_PAD_LEFT);
            $mqttPassword = 'sim-' . $stationId;

            $station = TenantStation::updateOrCreate(
                ['station_id' => $stationId],
                [
                    'tenant_id' => $tenant->id,
                    'mqtt_username' => 'sandbox_' . $stationId,
                    'mqtt_password_hash' => Hash::make($mqttPassword),
                    'mqtt_password_encrypted' => $mqttPassword,
                    'protocol_version' => config('sandbox.default_protocol_version', '0.2.1'),
                    'bay_count' => self::BAY_COUNT,
                ],
            );

            if ($station->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            if ($pkiConfigured && $station->station_cert === null) {
                try {
                    $certService->generateStationCert($station);
                } catch (\Throwable $e) {
                    $this->command?->warn("Cert failed for {$stationId}: {$e->getMessage()}");
                }
            }
        }

        $this->command?->info("Simulator stations: {$created} created, {$updated} updated.");
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ConformanceResult;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Contracts\CertificateServiceInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // WARNING: dev credentials — DO NOT use in production
        // Production: register via UI or API
        if (app()->isProduction() && Tenant::count() > 0) {
            $this->command?->info('Skipping dev seed — production with existing data');

            return;
        }

        $tenant = Tenant::firstOrCreate(
            ['email' => 'dev@ospp-standard.org'],
            [
                'name' => 'Development Tenant',
                'password' => 'password',
                'protocol_version' => config('sandbox.default_protocol_version', '0.2.1'),
                'validation_mode' => 'strict',
                'email_verified_at' => now(),
            ],
        );

        $station = TenantStation::firstOrCreate(
            ['station_id' => 'stn_00000001'],
            [
                'tenant_id' => $tenant->id,
                'mqtt_username' => 'sandbox_dev_001',
                'mqtt_password_hash' => Hash::make('dev-mqtt-password'),
                'mqtt_password_encrypted' => 'dev-mqtt-password',
                'protocol_version' => config('sandbox.default_protocol_version', '0.2.1'),
            ],
        );

        $certService = app(CertificateServiceInterface::class);
        if ($certService->isConfigured() && $station->station_cert === null) {
            try {
                $certService->generateStationCert($station);
                $this->command?->info("Generated cert for {$station->station_id}");
            } catch (\Throwable $e) {
                $this->command?->warn("Cert generation failed: {$e->getMessage()}");
            }
        }

        $this->call(SimulatorStationsSeeder::class);
        $this->call(ConformanceSeeder::class, parameters: ['tenantId' => $tenant->id]);
    }
}

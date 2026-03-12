<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ConformanceResult;
use App\Models\Tenant;
use App\Models\TenantStation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['email' => 'dev@ospp-standard.org'],
            [
                'name' => 'Development Tenant',
                'password' => 'password',
                'protocol_version' => '0.1.0',
                'validation_mode' => 'strict',
                'email_verified_at' => now(),
            ],
        );

        TenantStation::firstOrCreate(
            ['station_id' => 'stn_00000001'],
            [
                'tenant_id' => $tenant->id,
                'mqtt_username' => 'sandbox_dev_001',
                'mqtt_password_hash' => Hash::make('dev-mqtt-password'),
                'mqtt_password_encrypted' => 'dev-mqtt-password',
                'protocol_version' => '0.1.0',
            ],
        );

        $this->call(ConformanceSeeder::class, parameters: ['tenantId' => $tenant->id]);
    }
}

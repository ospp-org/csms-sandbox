<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantStation;
use Illuminate\Support\Facades\Hash;

final class MqttCredentialService
{
    public function generateForTenant(Tenant $tenant): TenantStation
    {
        $stationIndex = TenantStation::count() + 1;
        $stationId = 'stn_' . str_pad(dechex($stationIndex), 8, '0', STR_PAD_LEFT);

        $mqttUsername = 'sandbox_' . bin2hex(random_bytes(8));
        $mqttPassword = bin2hex(random_bytes(16));

        return TenantStation::create([
            'tenant_id' => $tenant->id,
            'station_id' => $stationId,
            'mqtt_username' => $mqttUsername,
            'mqtt_password_hash' => Hash::make($mqttPassword),
            'mqtt_password_encrypted' => $mqttPassword,
            'protocol_version' => $tenant->protocol_version,
        ]);
    }

    public function regeneratePassword(TenantStation $station): string
    {
        $newPassword = bin2hex(random_bytes(16));

        $station->update([
            'mqtt_password_hash' => Hash::make($newPassword),
            'mqtt_password_encrypted' => $newPassword,
        ]);

        return $newPassword;
    }

    public function getPlainPassword(TenantStation $station): string
    {
        return $station->mqtt_password_encrypted;
    }
}

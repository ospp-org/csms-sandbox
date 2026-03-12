<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantStation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantStation>
 */
final class TenantStationFactory extends Factory
{
    protected $model = TenantStation::class;

    public function definition(): array
    {
        $mqttPassword = Str::random(32);

        return [
            'tenant_id' => Tenant::factory(),
            'station_id' => 'stn_' . fake()->unique()->hexColor() . fake()->randomNumber(4, true),
            'mqtt_username' => 'sandbox_' . Str::random(8),
            'mqtt_password_hash' => Hash::make($mqttPassword),
            'mqtt_password_encrypted' => $mqttPassword,
            'protocol_version' => '0.1.0',
            'is_connected' => false,
        ];
    }

    public function connected(): static
    {
        return $this->state([
            'is_connected' => true,
            'last_connected_at' => now(),
        ]);
    }
}

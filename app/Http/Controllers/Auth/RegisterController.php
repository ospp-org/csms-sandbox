<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Tenant;
use App\Services\JwtService;
use App\Services\MqttCredentialService;
use Database\Seeders\ConformanceSeeder;
use Illuminate\Http\JsonResponse;

final class RegisterController extends Controller
{
    public function __invoke(
        RegisterRequest $request,
        JwtService $jwt,
        MqttCredentialService $mqttCredentials,
        ConformanceSeeder $conformanceSeeder,
    ): JsonResponse {
        $tenant = Tenant::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'protocol_version' => '0.1.0',
            'validation_mode' => 'strict',
            'email_verified_at' => now(),
        ]);

        $station = $mqttCredentials->generateForTenant($tenant);
        $plainPassword = $mqttCredentials->getPlainPassword($station);

        $conformanceSeeder->run($tenant->id);

        $token = $jwt->encode($tenant->id, $tenant->email);

        return new JsonResponse([
            'token' => $token,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'protocol_version' => $tenant->protocol_version,
                'validation_mode' => $tenant->validation_mode,
            ],
            'station' => [
                'station_id' => $station->station_id,
                'mqtt_host' => config('sandbox.mqtt_public_host'),
                'mqtt_port' => config('mqtt.tls_port'),
                'mqtt_username' => $station->mqtt_username,
                'mqtt_password' => $plainPassword,
            ],
        ], 201);
    }
}

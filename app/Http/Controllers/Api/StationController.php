<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\MqttCredentialService;
use App\Services\StationStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StationController extends Controller
{
    public function show(Request $request, MqttCredentialService $mqttCredentials): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $station = $tenant->station;

        if ($station === null) {
            return new JsonResponse(['error' => 'NO_STATION', 'message' => 'No station configured'], 404);
        }

        return new JsonResponse([
            'station_id' => $station->station_id,
            'mqtt' => [
                'host' => config('sandbox.mqtt_public_host'),
                'port_tls' => config('mqtt.tls_port'),
                'port_plain' => config('mqtt.port'),
                'username' => $station->mqtt_username,
                'password_available' => true,
            ],
            'topics' => [
                'publish' => config('mqtt.topic_prefix') . "/{$station->station_id}/" . config('mqtt.to_server_suffix'),
                'subscribe' => config('mqtt.topic_prefix') . "/{$station->station_id}/" . config('mqtt.to_station_suffix'),
            ],
            'status' => [
                'connected' => $station->is_connected,
                'last_connected_at' => $station->last_connected_at?->toIso8601String(),
                'last_boot_at' => $station->last_boot_at?->toIso8601String(),
                'firmware_version' => $station->firmware_version,
                'station_model' => $station->station_model,
                'station_vendor' => $station->station_vendor,
                'bay_count' => $station->bay_count,
            ],
            'protocol_version' => $station->protocol_version,
        ]);
    }

    public function regeneratePassword(
        Request $request,
        MqttCredentialService $mqttCredentials,
    ): JsonResponse {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $station = $tenant->station;

        if ($station === null) {
            return new JsonResponse(['error' => 'NO_STATION', 'message' => 'No station configured'], 404);
        }

        $newPassword = $mqttCredentials->regeneratePassword($station);

        return new JsonResponse([
            'mqtt_password' => $newPassword,
            'message' => 'Password regenerated. Old password is now invalid. Station must reconnect.',
        ]);
    }

    public function status(Request $request, StationStateService $stationState): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();
        $station = $tenant->station;

        if ($station === null) {
            return new JsonResponse(['error' => 'NO_STATION', 'message' => 'No station configured'], 404);
        }

        $connected = $stationState->isConnected($station->station_id);
        $lifecycle = $stationState->getLifecycle($station->station_id);
        $bays = $stationState->getAllBays($station->station_id);

        $bayData = [];
        foreach ($bays as $number => $bay) {
            $bayData[] = [
                'bay_number' => $number,
                'status' => $bay['status'],
                'session_id' => $bay['session_id'] ?: null,
                'reservation_id' => $bay['reservation_id'] ?: null,
            ];
        }

        return new JsonResponse([
            'connected' => $connected,
            'lifecycle' => $lifecycle,
            'last_heartbeat' => $stationState->getLastHeartbeat($station->station_id)
                ? date('Y-m-d\TH:i:s.000\Z', $stationState->getLastHeartbeat($station->station_id))
                : null,
            'bays' => $bayData,
        ]);
    }
}

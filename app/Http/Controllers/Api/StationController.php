<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\CommandService;
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

    public function showById(Request $request, string $stationId, StationStateService $stationState): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $station = TenantStation::where('tenant_id', $tenant->id)
            ->where('station_id', $stationId)
            ->first();

        if ($station === null) {
            return new JsonResponse(['error' => 'NOT_FOUND', 'message' => "Station not found: {$stationId}"], 404);
        }

        $lifecycle = $stationState->getLifecycle($stationId);
        $bays = $stationState->getAllBays($stationId);

        $bayData = [];
        foreach ($bays as $number => $bay) {
            $bayData[] = [
                'number' => $number,
                'status' => $bay['status'],
            ];
        }

        return new JsonResponse([
            'station_id' => $station->station_id,
            'protocol_version' => $station->protocol_version,
            'bay_count' => $station->bay_count,
            'is_connected' => $station->is_connected,
            'lifecycle' => $lifecycle,
            'bays' => $bayData,
            'last_connected_at' => $station->last_connected_at?->toIso8601String(),
            'last_boot_at' => $station->last_boot_at?->toIso8601String(),
        ]);
    }

    public function forceReject(Request $request, string $stationId): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $station = TenantStation::where('tenant_id', $tenant->id)
            ->where('station_id', $stationId)
            ->first();

        if ($station === null) {
            return new JsonResponse(['error' => 'NOT_FOUND', 'message' => "Station not found: {$stationId}"], 404);
        }

        $station->update(['force_boot_reject' => true]);

        return new JsonResponse([
            'message' => "Next BootNotification for {$stationId} will be Rejected.",
            'station_id' => $stationId,
        ]);
    }

    public function clearForceReject(Request $request, string $stationId): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $station = TenantStation::where('tenant_id', $tenant->id)
            ->where('station_id', $stationId)
            ->first();

        if ($station === null) {
            return new JsonResponse(['error' => 'NOT_FOUND', 'message' => "Station not found: {$stationId}"], 404);
        }

        $station->update(['force_boot_reject' => false]);

        return new JsonResponse([
            'message' => "Force reject cleared for {$stationId}.",
            'station_id' => $stationId,
        ]);
    }

    public function forcePending(Request $request, string $stationId): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $station = TenantStation::where('tenant_id', $tenant->id)
            ->where('station_id', $stationId)
            ->first();

        if ($station === null) {
            return new JsonResponse(['error' => 'NOT_FOUND', 'message' => "Station not found: {$stationId}"], 404);
        }

        $retryInterval = (int) ($request->input('retry_interval', 30));

        $station->update([
            'force_boot_pending' => true,
            'boot_retry_interval' => $retryInterval,
        ]);

        return new JsonResponse([
            'message' => "Next BootNotification for {$stationId} will be Pending with retryInterval={$retryInterval}s.",
            'station_id' => $stationId,
        ]);
    }

    public function clearForcePending(Request $request, string $stationId): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $station = TenantStation::where('tenant_id', $tenant->id)
            ->where('station_id', $stationId)
            ->first();

        if ($station === null) {
            return new JsonResponse(['error' => 'NOT_FOUND', 'message' => "Station not found: {$stationId}"], 404);
        }

        $station->update(['force_boot_pending' => false, 'boot_retry_interval' => null]);

        return new JsonResponse([
            'message' => "Force pending cleared for {$stationId}.",
            'station_id' => $stationId,
        ]);
    }

    public function triggerDataTransfer(Request $request, string $stationId, CommandService $commandService): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->user();

        $station = TenantStation::where('tenant_id', $tenant->id)
            ->where('station_id', $stationId)
            ->first();

        if ($station === null) {
            return new JsonResponse(['error' => 'NOT_FOUND', 'message' => "Station not found: {$stationId}"], 404);
        }

        $result = $commandService->send(
            tenantId: $tenant->id,
            action: 'DataTransfer',
            parameters: [
                'vendorId' => (string) $request->input('vendor_id', 'com.ospp.sandbox'),
                'dataId' => (string) $request->input('data_id', 'ping'),
                'data' => $request->input('data', new \stdClass()),
            ],
            stationId: $stationId,
        );

        if (! $result->success) {
            return new JsonResponse([
                'error' => $result->errorCode,
                'message' => $result->errorText,
            ], $result->errorCode === 'STATION_NOT_CONNECTED' ? 409 : 400);
        }

        return new JsonResponse([
            'message' => 'DataTransfer sent.',
            'station_id' => $stationId,
            'message_id' => $result->messageId,
        ]);
    }
}

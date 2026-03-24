<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mqtt;

use App\Http\Controllers\Controller;
use App\Models\TenantStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MqttAclController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $username = $request->input('username', '');
        $topic = $request->input('topic', '');
        $action = $request->input('action', '');
        $cn = $request->input('cn', '');

        $station = TenantStation::where('mqtt_username', $username)->first();

        if ($station === null) {
            return new JsonResponse(['result' => 'deny']);
        }

        // Defense-in-depth: if cert CN is present, it must match station_id.
        // CN is empty for plain TCP (port 1883, dev only) — skip check.
        // On TLS (port 8883), fail_if_no_peer_cert ensures CN is always present.
        if ($cn !== '' && $cn !== $station->station_id) {
            return new JsonResponse(['result' => 'deny']);
        }

        $stationId = $station->station_id;
        $prefix = config('mqtt.topic_prefix');

        $allowedPublish = "{$prefix}/{$stationId}/" . config('mqtt.to_server_suffix');
        $allowedSubscribe = "{$prefix}/{$stationId}/" . config('mqtt.to_station_suffix');

        if ($action === 'publish' && $topic === $allowedPublish) {
            return new JsonResponse(['result' => 'allow']);
        }

        if ($action === 'subscribe' && $topic === $allowedSubscribe) {
            return new JsonResponse(['result' => 'allow']);
        }

        return new JsonResponse(['result' => 'deny']);
    }
}

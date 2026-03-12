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

        $station = TenantStation::where('mqtt_username', $username)->first();

        if ($station === null) {
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

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mqtt;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMqttMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MqttWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $topic = (string) $request->input('topic', '');
        $payload = $request->input('payload');

        if ($topic === '' || $payload === null) {
            return new JsonResponse(['error' => 'missing_fields'], 400);
        }

        if (is_array($payload)) {
            $payload = (string) json_encode($payload);
        }

        if (! is_string($payload) || $payload === '') {
            return new JsonResponse(['error' => 'invalid_payload'], 400);
        }

        $parts = explode('/', $topic);
        $stationId = $parts[3] ?? 'unknown';

        ProcessMqttMessage::dispatch($stationId, $topic, $payload);

        return new JsonResponse(['status' => 'ok']);
    }
}

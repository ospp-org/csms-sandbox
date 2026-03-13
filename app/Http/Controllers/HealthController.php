<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'emqx' => $this->checkEmqx(),
        ];

        $allHealthy = ! in_array('error', array_values($checks), true);

        $status = $allHealthy ? 'healthy' : 'degraded';
        $httpCode = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $status,
            'checks' => $checks,
        ], $httpCode);
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();

            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function checkRedis(): string
    {
        try {
            Cache::store('redis')->put('health_check', true, 10);

            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function checkEmqx(): string
    {
        $apiUrl = config('services.emqx.api_url');

        if (empty($apiUrl)) {
            return 'unavailable';
        }

        try {
            $response = Http::timeout(3)
                ->withBasicAuth(
                    (string) config('services.emqx.api_username'),
                    (string) config('services.emqx.api_password'),
                )
                ->get($apiUrl . '/status');

            return $response->successful() ? 'ok' : 'error';
        } catch (\Throwable) {
            return 'unavailable';
        }
    }
}

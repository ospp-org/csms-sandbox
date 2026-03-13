<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

final class StatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $services = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'emqx' => $this->checkEmqx(),
            'queue' => $this->checkQueue(),
        ];

        $allOk = ! in_array('error', $services, true);

        return new JsonResponse([
            'status' => $allOk ? 'operational' : 'degraded',
            'version' => config('app.version', '0.1.1'),
            'services' => $services,
            'stats' => $this->getStats(),
        ], $allOk ? 200 : 503);
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
            Cache::store('redis')->put('status_check', true, 10);

            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function checkEmqx(): string
    {
        try {
            $response = Http::timeout(3)->get(config('services.emqx.api_url') . '/status');

            return $response->successful() ? 'ok' : 'error';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function checkQueue(): string
    {
        try {
            /** @var \Illuminate\Redis\Connections\Connection $redis */
            $redis = Redis::connection();
            $length = (int) $redis->llen('queues:mqtt-messages');

            return $length < 1000 ? 'ok' : 'degraded';
        } catch (\Throwable) {
            return 'error';
        }
    }

    /**
     * @return array{tenants: int, messages_24h: int, active_stations: int}
     */
    private function getStats(): array
    {
        try {
            return [
                'tenants' => Tenant::count(),
                'messages_24h' => MessageLog::where('created_at', '>=', now()->subDay())->count(),
                'active_stations' => MessageLog::where('created_at', '>=', now()->subMinutes(5))
                    ->distinct('station_id')
                    ->count('station_id'),
            ];
        } catch (\Throwable) {
            return [
                'tenants' => 0,
                'messages_24h' => 0,
                'active_stations' => 0,
            ];
        }
    }
}

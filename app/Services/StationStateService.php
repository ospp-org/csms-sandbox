<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Redis;

final class StationStateService
{
    private const PREFIX = 'sandbox:station:';
    private const CONNECTION_TTL = 90;

    public function getLifecycle(string $stationId): string
    {
        return (string) Redis::hget(self::PREFIX . $stationId, 'lifecycle') ?: 'offline';
    }

    public function setLifecycle(string $stationId, string $lifecycle): void
    {
        Redis::hset(self::PREFIX . $stationId, 'lifecycle', $lifecycle);
    }

    public function getBayStatus(string $stationId, int $bayNumber): string
    {
        return (string) Redis::hget(self::PREFIX . $stationId . ':bay:' . $bayNumber, 'status') ?: 'Unknown';
    }

    public function setBayStatus(string $stationId, int $bayNumber, string $status): void
    {
        Redis::hset(self::PREFIX . $stationId . ':bay:' . $bayNumber, 'status', $status);
    }

    public function getBaySession(string $stationId, int $bayNumber): ?string
    {
        $session = Redis::hget(self::PREFIX . $stationId . ':bay:' . $bayNumber, 'session_id');

        return $session ?: null;
    }

    public function setBaySession(string $stationId, int $bayNumber, ?string $sessionId, ?string $serviceId = null): void
    {
        $key = self::PREFIX . $stationId . ':bay:' . $bayNumber;
        Redis::hset($key, 'session_id', $sessionId ?? '');
        Redis::hset($key, 'service_id', $serviceId ?? '');
    }

    public function getBayReservation(string $stationId, int $bayNumber): ?string
    {
        $reservation = Redis::hget(self::PREFIX . $stationId . ':bay:' . $bayNumber, 'reservation_id');

        return $reservation ?: null;
    }

    public function setBayReservation(string $stationId, int $bayNumber, ?string $reservationId): void
    {
        Redis::hset(self::PREFIX . $stationId . ':bay:' . $bayNumber, 'reservation_id', $reservationId ?? '');
    }

    /**
     * @return array<int, array{status: string, session_id: ?string, reservation_id: ?string}>
     */
    public function getAllBays(string $stationId): array
    {
        $bayCount = (int) Redis::hget(self::PREFIX . $stationId, 'bay_count') ?: 0;
        $bays = [];

        for ($i = 1; $i <= $bayCount; $i++) {
            $bays[$i] = [
                'status' => $this->getBayStatus($stationId, $i),
                'session_id' => $this->getBaySession($stationId, $i),
                'reservation_id' => $this->getBayReservation($stationId, $i),
            ];
        }

        return $bays;
    }

    /**
     * @return array<string, string>
     */
    public function getConfig(string $stationId): array
    {
        $config = Redis::hgetall(self::PREFIX . $stationId . ':config');

        return is_array($config) ? $config : [];
    }

    /**
     * @param array<string, string> $keys
     */
    public function setConfig(string $stationId, array $keys): void
    {
        foreach ($keys as $key => $value) {
            Redis::hset(self::PREFIX . $stationId . ':config', $key, $value);
        }
    }

    public function refreshConnection(string $stationId): void
    {
        Redis::setex(self::PREFIX . $stationId . ':connected', self::CONNECTION_TTL, '1');
    }

    public function isConnected(string $stationId): bool
    {
        return Redis::get(self::PREFIX . $stationId . ':connected') === '1';
    }

    public function getHeartbeatInterval(string $stationId): int
    {
        return (int) Redis::hget(self::PREFIX . $stationId, 'heartbeat_interval') ?: 30;
    }

    public function setHeartbeatInterval(string $stationId, int $interval): void
    {
        Redis::hset(self::PREFIX . $stationId, 'heartbeat_interval', (string) $interval);
    }

    public function getLastHeartbeat(string $stationId): ?int
    {
        $ts = Redis::hget(self::PREFIX . $stationId, 'last_heartbeat');

        return $ts ? (int) $ts : null;
    }

    public function setLastHeartbeat(string $stationId, int $timestamp): void
    {
        Redis::hset(self::PREFIX . $stationId, 'last_heartbeat', (string) $timestamp);
    }

    public function resetState(string $stationId, int $bayCount): void
    {
        Redis::hset(self::PREFIX . $stationId, 'lifecycle', 'online');
        Redis::hset(self::PREFIX . $stationId, 'bay_count', (string) $bayCount);
        Redis::hset(self::PREFIX . $stationId, 'heartbeat_interval', '30');

        for ($i = 1; $i <= $bayCount; $i++) {
            $key = self::PREFIX . $stationId . ':bay:' . $i;
            Redis::del($key);
            Redis::hset($key, 'status', 'Unknown');
            Redis::hset($key, 'session_id', '');
            Redis::hset($key, 'reservation_id', '');
        }

        $this->refreshConnection($stationId);
    }
}

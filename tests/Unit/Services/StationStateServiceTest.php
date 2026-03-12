<?php

declare(strict_types=1);

use App\Services\StationStateService;
use Illuminate\Support\Facades\Redis;

beforeEach(function (): void {
    Redis::flushdb();
});

test('lifecycle defaults to offline', function (): void {
    $service = app(StationStateService::class);

    expect($service->getLifecycle('stn_new'))->toBe('offline');
});

test('set and get lifecycle', function (): void {
    $service = app(StationStateService::class);

    $service->setLifecycle('stn_001', 'online');
    expect($service->getLifecycle('stn_001'))->toBe('online');

    $service->setLifecycle('stn_001', 'resetting');
    expect($service->getLifecycle('stn_001'))->toBe('resetting');
});

test('connection tracking with TTL', function (): void {
    $service = app(StationStateService::class);

    expect($service->isConnected('stn_001'))->toBeFalse();

    $service->refreshConnection('stn_001');
    expect($service->isConnected('stn_001'))->toBeTrue();
});

test('resetState initializes all bay data', function (): void {
    $service = app(StationStateService::class);

    $service->resetState('stn_001', 3);

    expect($service->getLifecycle('stn_001'))->toBe('online');
    expect($service->isConnected('stn_001'))->toBeTrue();

    $bays = $service->getAllBays('stn_001');
    expect($bays)->toHaveCount(3);
    expect($bays[1]['status'])->toBe('Unknown');
    expect($bays[2]['status'])->toBe('Unknown');
    expect($bays[3]['status'])->toBe('Unknown');
});

test('bay status management', function (): void {
    $service = app(StationStateService::class);
    $service->resetState('stn_001', 2);

    $service->setBayStatus('stn_001', 1, 'Available');
    expect($service->getBayStatus('stn_001', 1))->toBe('Available');
    expect($service->getBayStatus('stn_001', 2))->toBe('Unknown');
});

test('bay session management', function (): void {
    $service = app(StationStateService::class);
    $service->resetState('stn_001', 1);

    expect($service->getBaySession('stn_001', 1))->toBeNull();

    $service->setBaySession('stn_001', 1, 'sess_123', 'svc_wash');
    expect($service->getBaySession('stn_001', 1))->toBe('sess_123');

    $service->setBaySession('stn_001', 1, null);
    expect($service->getBaySession('stn_001', 1))->toBeNull();
});

test('heartbeat interval and timestamp', function (): void {
    $service = app(StationStateService::class);

    expect($service->getHeartbeatInterval('stn_001'))->toBe(30);
    expect($service->getLastHeartbeat('stn_001'))->toBeNull();

    $service->setHeartbeatInterval('stn_001', 60);
    expect($service->getHeartbeatInterval('stn_001'))->toBe(60);

    $now = time();
    $service->setLastHeartbeat('stn_001', $now);
    expect($service->getLastHeartbeat('stn_001'))->toBe($now);
});

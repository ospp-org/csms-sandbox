<?php

declare(strict_types=1);

use App\Conformance\Rules\ReservationExpiryRule;
use App\Dto\HandlerContext;
use App\Models\CommandHistory;
use App\Models\Tenant;
use App\Services\StationStateService;

test('ReservationExpiryRule passes when command has future expirationTime', function (): void {
    $rule = new ReservationExpiryRule();
    $tenant = Tenant::factory()->create();

    CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_re000001',
        'action' => 'ReserveBay',
        'message_id' => 'msg_re_001',
        'payload' => ['bayNumber' => 1, 'expirationTime' => now()->addHour()->toIso8601String()],
        'status' => 'sent',
    ]);

    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_re000001',
        action: 'ReserveBay',
        messageId: 'msg_re_001',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeTrue();
});

test('ReservationExpiryRule fails when command has past expirationTime', function (): void {
    $rule = new ReservationExpiryRule();
    $tenant = Tenant::factory()->create();

    CommandHistory::create([
        'tenant_id' => $tenant->id,
        'station_id' => 'stn_re000002',
        'action' => 'ReserveBay',
        'message_id' => 'msg_re_002',
        'payload' => ['bayNumber' => 1, 'expirationTime' => now()->subHour()->toIso8601String()],
        'status' => 'sent',
    ]);

    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_re000002',
        action: 'ReserveBay',
        messageId: 'msg_re_002',
        messageType: 'Response',
        payload: ['status' => 'Accepted'],
        envelope: [],
        protocolVersion: '0.2.1',
    );

    $result = $rule->check($context, app(StationStateService::class));

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toBe('Reservation expirationTime is in the past');
});

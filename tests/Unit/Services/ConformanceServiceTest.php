<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Dto\ValidationResult;
use App\Models\ConformanceResult;
use App\Models\Tenant;
use App\Models\TenantStation;
use App\Services\ConformanceService;

test('recordResult creates passed result when schema and behavior pass', function (): void {
    $tenant = Tenant::factory()->create();
    $service = app(ConformanceService::class);

    $service->recordResult(
        $tenant->id,
        'stn_cs000001',
        config('sandbox.default_protocol_version'),
        'BootNotification',
        ValidationResult::valid(),
        [new RuleResult(true, 'boot_first'), new RuleResult(true, 'envelope_format')],
        ['stationId' => 'stn_cs000001'],
    );

    $result = ConformanceResult::where('station_id', 'stn_cs000001')
        ->where('action', 'BootNotification')
        ->first();

    expect($result->status)->toBe('passed');
    expect($result->error_details)->toBeNull();
    expect($result->behavior_checks)->toHaveCount(2);
});

test('recordResult creates failed result when schema fails', function (): void {
    $tenant = Tenant::factory()->create();
    $service = app(ConformanceService::class);

    $service->recordResult(
        $tenant->id,
        'stn_cs000002',
        config('sandbox.default_protocol_version'),
        'Heartbeat',
        ValidationResult::invalid([['path' => '/payload', 'message' => 'Missing field', 'keyword' => 'required']]),
        [new RuleResult(true, 'heartbeat_timing')],
        [],
    );

    $result = ConformanceResult::where('station_id', 'stn_cs000002')
        ->where('action', 'Heartbeat')
        ->first();

    expect($result->status)->toBe('failed');
    expect($result->error_details)->not->toBeNull();
});

test('recordResult creates partial result when schema passes but behavior fails', function (): void {
    $tenant = Tenant::factory()->create();
    $service = app(ConformanceService::class);

    $service->recordResult(
        $tenant->id,
        'stn_cs000003',
        config('sandbox.default_protocol_version'),
        'StatusNotification',
        ValidationResult::valid(),
        [new RuleResult(true, 'envelope_format'), new RuleResult(false, 'bay_transition', 'Invalid transition')],
        [],
    );

    $result = ConformanceResult::where('station_id', 'stn_cs000003')
        ->where('action', 'StatusNotification')
        ->first();

    expect($result->status)->toBe('partial');
});

test('getReport returns correct scoring', function (): void {
    $tenant = Tenant::factory()->create();
    $stationId = 'stn_cs000004';

    ConformanceResult::factory()->for($tenant)->create(['station_id' => $stationId, 'action' => 'BootNotification', 'status' => 'passed', 'last_tested_at' => now()]);
    ConformanceResult::factory()->for($tenant)->create(['station_id' => $stationId, 'action' => 'Heartbeat', 'status' => 'passed', 'last_tested_at' => now()]);
    ConformanceResult::factory()->for($tenant)->create(['station_id' => $stationId, 'action' => 'StatusNotification', 'status' => 'failed', 'last_tested_at' => now()]);
    ConformanceResult::factory()->for($tenant)->create(['station_id' => $stationId, 'action' => 'DataTransfer', 'status' => 'not_tested']);

    $service = app(ConformanceService::class);
    $report = $service->getReport($stationId, config('sandbox.default_protocol_version'));

    expect($report->passed)->toBe(2);
    expect($report->failed)->toBe(1);
    expect($report->notTested)->toBe(24); // 27 config actions - 3 tested
    expect($report->totalTested)->toBe(3);
    expect($report->percentage)->toBe(66.7);
});

test('reset clears all results', function (): void {
    $tenant = Tenant::factory()->create();
    $stationId = 'stn_cs000005';

    ConformanceResult::factory()->for($tenant)->create(['station_id' => $stationId, 'action' => 'BootNotification', 'status' => 'passed', 'last_tested_at' => now()]);
    ConformanceResult::factory()->for($tenant)->create(['station_id' => $stationId, 'action' => 'Heartbeat', 'status' => 'failed', 'last_tested_at' => now()]);

    $service = app(ConformanceService::class);
    $count = $service->reset($stationId, config('sandbox.default_protocol_version'));

    expect($count)->toBe(2);

    $results = ConformanceResult::where('station_id', $stationId)->get();
    expect($results->every(fn ($r) => $r->status === 'not_tested'))->toBeTrue();
});

test('evaluate runs all rules and records result', function (): void {
    $tenant = Tenant::factory()->create();
    TenantStation::factory()->for($tenant)->create(['station_id' => 'stn_cs000006']);
    $service = app(ConformanceService::class);

    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_cs000006',
        action: 'BootNotification',
        messageId: 'msg_ev_001',
        messageType: 'Request',
        payload: [],
        envelope: [
            'action' => 'BootNotification',
            'messageId' => 'msg_ev_001',
            'messageType' => 'Request',
            'source' => 'Station',
            'protocolVersion' => '0.2.1',
            'timestamp' => '2026-03-09T10:00:05.000Z',
            'payload' => [],
        ],
        protocolVersion: '0.2.1',
    );

    $results = $service->evaluate($context, ValidationResult::valid());

    expect($results)->toBeArray();
    expect(count($results))->toBe(14);

    $this->assertDatabaseHas('conformance_results', [
        'station_id' => 'stn_cs000006',
        'action' => 'BootNotification',
    ]);
});

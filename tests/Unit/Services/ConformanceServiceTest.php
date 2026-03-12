<?php

declare(strict_types=1);

use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Dto\ValidationResult;
use App\Models\ConformanceResult;
use App\Models\Tenant;
use App\Services\ConformanceService;

test('recordResult creates passed result when schema and behavior pass', function (): void {
    $tenant = Tenant::factory()->create();
    $service = app(ConformanceService::class);

    $service->recordResult(
        $tenant->id,
        '0.1.0',
        'BootNotification',
        ValidationResult::valid(),
        [new RuleResult(true, 'boot_first'), new RuleResult(true, 'envelope_format')],
        ['stationId' => 'stn_a0000001'],
    );

    $result = ConformanceResult::where('tenant_id', $tenant->id)
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
        '0.1.0',
        'Heartbeat',
        ValidationResult::invalid([['path' => '/payload', 'message' => 'Missing field', 'keyword' => 'required']]),
        [new RuleResult(true, 'heartbeat_timing')],
        [],
    );

    $result = ConformanceResult::where('tenant_id', $tenant->id)
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
        '0.1.0',
        'StatusNotification',
        ValidationResult::valid(),
        [new RuleResult(true, 'envelope_format'), new RuleResult(false, 'bay_transition', 'Invalid transition')],
        [],
    );

    $result = ConformanceResult::where('tenant_id', $tenant->id)
        ->where('action', 'StatusNotification')
        ->first();

    expect($result->status)->toBe('partial');
});

test('getReport returns correct scoring', function (): void {
    $tenant = Tenant::factory()->create();

    ConformanceResult::factory()->for($tenant)->create(['action' => 'BootNotification', 'status' => 'passed', 'last_tested_at' => now()]);
    ConformanceResult::factory()->for($tenant)->create(['action' => 'Heartbeat', 'status' => 'passed', 'last_tested_at' => now()]);
    ConformanceResult::factory()->for($tenant)->create(['action' => 'StatusNotification', 'status' => 'failed', 'last_tested_at' => now()]);
    ConformanceResult::factory()->for($tenant)->create(['action' => 'DataTransfer', 'status' => 'not_tested']);

    $service = app(ConformanceService::class);
    $report = $service->getReport($tenant->id, '0.1.0');

    expect($report->passed)->toBe(2);
    expect($report->failed)->toBe(1);
    expect($report->notTested)->toBe(1);
    expect($report->totalTested)->toBe(3);
    expect($report->percentage)->toBe(66.7);
});

test('reset clears all results', function (): void {
    $tenant = Tenant::factory()->create();

    ConformanceResult::factory()->for($tenant)->create(['action' => 'BootNotification', 'status' => 'passed', 'last_tested_at' => now()]);
    ConformanceResult::factory()->for($tenant)->create(['action' => 'Heartbeat', 'status' => 'failed', 'last_tested_at' => now()]);

    $service = app(ConformanceService::class);
    $count = $service->reset($tenant->id, '0.1.0');

    expect($count)->toBe(2);

    $results = ConformanceResult::where('tenant_id', $tenant->id)->get();
    expect($results->every(fn ($r) => $r->status === 'not_tested'))->toBeTrue();
});

test('evaluate runs all rules and records result', function (): void {
    $tenant = Tenant::factory()->create();
    $service = app(ConformanceService::class);

    $context = new HandlerContext(
        tenantId: $tenant->id,
        stationId: 'stn_ev000001',
        action: 'BootNotification',
        messageId: 'msg_ev_001',
        messageType: 'Request',
        payload: [],
        envelope: [
            'action' => 'BootNotification',
            'messageId' => 'msg_ev_001',
            'messageType' => 'Request',
            'source' => 'Station',
            'protocolVersion' => '0.1.0',
            'timestamp' => '2026-03-09T10:00:05.000Z',
            'payload' => [],
        ],
        protocolVersion: '0.1.0',
    );

    $results = $service->evaluate($context, ValidationResult::valid());

    expect($results)->toBeArray();
    expect(count($results))->toBe(7);

    $this->assertDatabaseHas('conformance_results', [
        'tenant_id' => $tenant->id,
        'action' => 'BootNotification',
    ]);
});

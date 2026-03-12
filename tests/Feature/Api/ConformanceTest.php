<?php

declare(strict_types=1);

use App\Models\ConformanceResult;
use App\Models\Tenant;

test('GET /api/v1/conformance returns conformance report', function (): void {
    $tenant = Tenant::factory()->create();

    ConformanceResult::factory()->for($tenant)->create([
        'action' => 'BootNotification',
        'status' => 'passed',
        'last_tested_at' => now(),
        'behavior_checks' => [['rule' => 'boot_first', 'passed' => true, 'detail' => null]],
    ]);
    ConformanceResult::factory()->for($tenant)->create([
        'action' => 'Heartbeat',
        'status' => 'failed',
        'last_tested_at' => now(),
        'error_details' => [['path' => '/payload', 'message' => 'Missing field']],
    ]);

    $response = $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/conformance');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'protocol_version',
            'score' => ['passed', 'failed', 'partial', 'not_tested', 'total_tested', 'percentage'],
            'categories',
            'results',
        ])
        ->assertJsonPath('score.passed', 1)
        ->assertJsonPath('score.failed', 1)
        ->assertJsonPath('score.total_tested', 2);
});

test('GET /api/v1/conformance/{action} returns single result', function (): void {
    $tenant = Tenant::factory()->create();

    ConformanceResult::factory()->for($tenant)->create([
        'action' => 'BootNotification',
        'status' => 'passed',
        'last_tested_at' => now(),
    ]);

    $response = $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/conformance/BootNotification');

    $response->assertStatus(200)
        ->assertJsonPath('action', 'BootNotification')
        ->assertJsonPath('status', 'passed');
});

test('GET /api/v1/conformance/{action} returns 404 for unknown', function (): void {
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/conformance/FakeAction');

    $response->assertStatus(404);
});

test('POST /api/v1/conformance/reset resets all results', function (): void {
    $tenant = Tenant::factory()->create();

    ConformanceResult::factory()->for($tenant)->create([
        'action' => 'BootNotification',
        'status' => 'passed',
        'last_tested_at' => now(),
    ]);
    ConformanceResult::factory()->for($tenant)->create([
        'action' => 'Heartbeat',
        'status' => 'failed',
        'last_tested_at' => now(),
    ]);

    $response = $this->actingAs($tenant, 'jwt')
        ->postJson('/api/v1/conformance/reset');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Conformance results reset')
        ->assertJsonPath('actions_reset', 2);

    $this->assertDatabaseMissing('conformance_results', [
        'tenant_id' => $tenant->id,
        'status' => 'passed',
    ]);
});

test('GET /api/v1/conformance/export/json returns downloadable JSON', function (): void {
    $tenant = Tenant::factory()->create();

    ConformanceResult::factory()->for($tenant)->create([
        'action' => 'BootNotification',
        'status' => 'passed',
        'last_tested_at' => now(),
    ]);

    $response = $this->actingAs($tenant, 'jwt')
        ->getJson('/api/v1/conformance/export/json');

    $response->assertStatus(200)
        ->assertHeader('Content-Disposition', 'attachment; filename="conformance-report.json"');
});

test('GET /api/v1/conformance/export/pdf returns PDF', function (): void {
    $tenant = Tenant::factory()->create();

    ConformanceResult::factory()->for($tenant)->create([
        'action' => 'BootNotification',
        'status' => 'passed',
        'last_tested_at' => now(),
    ]);

    $response = $this->actingAs($tenant, 'jwt')
        ->get('/api/v1/conformance/export/pdf');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/pdf');
});

test('conformance endpoints require authentication', function (): void {
    $this->getJson('/api/v1/conformance')->assertStatus(401);
    $this->postJson('/api/v1/conformance/reset')->assertStatus(401);
    $this->getJson('/api/v1/conformance/export/pdf')->assertStatus(401);
    $this->getJson('/api/v1/conformance/export/json')->assertStatus(401);
});

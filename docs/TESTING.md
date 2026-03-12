# CSMS Sandbox — Testing Strategy

---

## Framework

- **Pest 3** — all tests
- **Parallel execution:** `pest --parallel --processes=N`
- **Database:** RefreshDatabase trait (each test gets clean DB)
- **Redis:** Flushed before each test via `Redis::flushdb()` in setUp
- **Queue:** `Queue::fake()` for unit tests, real queue for integration tests

---

## Test Structure

```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── RegisterTest.php
│   │   ├── LoginTest.php
│   │   ├── GoogleOAuthTest.php
│   │   └── LogoutTest.php
│   │
│   ├── Mqtt/
│   │   ├── MqttWebhookTest.php
│   │   ├── MqttAuthTest.php
│   │   ├── MqttAclTest.php
│   │   └── MqttPipelineTest.php
│   │
│   ├── Handlers/
│   │   ├── BootNotificationTest.php
│   │   ├── HeartbeatTest.php
│   │   ├── StatusNotificationTest.php
│   │   ├── MeterValuesTest.php
│   │   ├── DataTransferTest.php
│   │   ├── SecurityEventTest.php
│   │   ├── SignCertificateTest.php
│   │   ├── StartServiceResponseTest.php
│   │   ├── StopServiceResponseTest.php
│   │   ├── ReserveBayResponseTest.php
│   │   └── ... (one per handler)
│   │
│   ├── Api/
│   │   ├── StationTest.php
│   │   ├── MessageTest.php
│   │   ├── CommandTest.php
│   │   ├── ConformanceTest.php
│   │   └── SettingsTest.php
│   │
│   └── Conformance/
│       ├── SchemaValidationTest.php
│       └── BehaviorRulesTest.php
│
└── Unit/
    ├── Handlers/
    │   ├── BootNotificationHandlerTest.php
    │   ├── HeartbeatHandlerTest.php
    │   └── ... (one per handler)
    │
    ├── Services/
    │   ├── StationStateServiceTest.php
    │   ├── ConformanceServiceTest.php
    │   ├── SchemaValidationServiceTest.php
    │   ├── MqttCredentialServiceTest.php
    │   └── CommandServiceTest.php
    │
    └── Conformance/
        ├── BootFirstRuleTest.php
        ├── HeartbeatTimingRuleTest.php
        ├── SessionStateRuleTest.php
        ├── BayTransitionRuleTest.php
        ├── ResponseTimingRuleTest.php
        ├── IdempotencyRuleTest.php
        └── ConformanceScorerTest.php
```

---

## What to Test Per Layer

### Auth (Feature Tests)

```php
// RegisterTest.php
test('POST /auth/register creates tenant with station', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['token', 'tenant', 'station'])
        ->assertJsonPath('station.mqtt_host', 'csms-sandbox.ospp-standard.org');

    $this->assertDatabaseHas('tenants', ['email' => 'test@example.com']);
    $this->assertDatabaseHas('tenant_stations', ['tenant_id' => $response->json('tenant.id')]);
});

test('POST /auth/register fails with duplicate email', function () { ... });
test('POST /auth/register fails with weak password', function () { ... });

// LoginTest.php
test('POST /auth/login returns JWT for valid credentials', function () { ... });
test('POST /auth/login fails with wrong password', function () { ... });
test('POST /auth/login rate limited after 30 attempts', function () { ... });
```

### MQTT Pipeline (Feature Tests)

```php
// MqttWebhookTest.php
test('POST /internal/mqtt/webhook dispatches ProcessMqttMessage job', function () {
    Queue::fake([ProcessMqttMessage::class]);

    $response = $this->postJson('/internal/mqtt/webhook', [
        'topic' => 'ospp/v1/stations/stn_00000001/to-server',
        'payload' => json_encode([...valid boot notification...]),
    ], [
        'X-Webhook-Secret' => config('mqtt.webhook.secret'),
    ]);

    $response->assertStatus(200);
    Queue::assertPushed(ProcessMqttMessage::class);
});

test('webhook rejects without secret header', function () { ... });
test('webhook rejects empty payload', function () { ... });

// MqttAuthTest.php
test('POST /internal/mqtt/auth allows valid credentials', function () {
    $station = TenantStation::factory()->create([
        'mqtt_username' => 'sandbox_test',
        'mqtt_password_hash' => Hash::make('test-password'),
    ]);

    $response = $this->postJson('/internal/mqtt/auth', [
        'username' => 'sandbox_test',
        'password' => 'test-password',
    ], ['X-Webhook-Secret' => config('mqtt.webhook.secret')]);

    $response->assertJsonPath('result', 'allow');
});

test('POST /internal/mqtt/auth denies wrong password', function () { ... });
test('POST /internal/mqtt/auth denies unknown username', function () { ... });

// MqttAclTest.php
test('ACL allows publish to own station to-server topic', function () { ... });
test('ACL denies publish to other station topic', function () { ... });
test('ACL allows subscribe to own station to-station topic', function () { ... });
test('ACL denies subscribe to other station topic', function () { ... });
```

### Handlers (Unit Tests)

```php
// BootNotificationHandlerTest.php
test('BootNotification returns Accepted with serverTime', function () {
    $handler = new BootNotificationHandler($stationState, $publisher);

    $context = new HandlerContext(
        tenantId: 'tenant-uuid',
        stationId: 'stn_00000001',
        action: 'BootNotification',
        messageId: 'msg_001',
        messageType: 'Request',
        payload: [
            'stationModel' => 'WashPro 5000',
            'stationVendor' => 'Test',
            'firmwareVersion' => '1.0.0',
            'bayCount' => 4,
        ],
        envelope: [...],
        protocolVersion: '0.1.0',
    );

    $result = $handler->handle($context);

    expect($result->success)->toBeTrue();
    expect($result->responsePayload['status'])->toBe('Accepted');
    expect($result->responsePayload)->toHaveKey('serverTime');
    expect($result->responsePayload)->toHaveKey('heartbeatIntervalSec');
});

test('BootNotification initializes bay states in Redis', function () { ... });
test('BootNotification updates station info in DB', function () { ... });
```

### Conformance (Unit Tests)

```php
// HeartbeatTimingRuleTest.php
test('passes when heartbeat within ±10% of interval', function () {
    $rule = new HeartbeatTimingRule();

    // Set last heartbeat 30s ago, interval is 30s
    $state->setLastHeartbeat('stn_001', time() - 30);
    $state->setHeartbeatInterval('stn_001', 30);

    $context = makeContext('Heartbeat');
    $result = $rule->check($context, $state);

    expect($result->passed)->toBeTrue();
});

test('fails when heartbeat drift exceeds 10%', function () {
    $rule = new HeartbeatTimingRule();

    // 45s since last heartbeat, interval is 30s (50% drift)
    $state->setLastHeartbeat('stn_001', time() - 45);
    $state->setHeartbeatInterval('stn_001', 30);

    $context = makeContext('Heartbeat');
    $result = $rule->check($context, $state);

    expect($result->passed)->toBeFalse();
    expect($result->detail)->toContain('45s');
});

// BayTransitionRuleTest.php
test('allows Unknown → Available transition', function () { ... });
test('rejects Available → Finishing transition', function () { ... });
test('allows Occupied → Finishing transition', function () { ... });

// ConformanceScorerTest.php
test('calculates correct percentage', function () {
    // 18 passed, 4 failed, 4 not tested
    $results = collect([...]);
    $report = new ConformanceReport($results, '0.1.0');

    expect($report->percentage)->toBe(81.8);
    expect($report->passed)->toBe(18);
    expect($report->totalTested)->toBe(22);
});
```

### API Endpoints (Feature Tests)

```php
// CommandTest.php
test('POST /commands/StartService sends command to station', function () {
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create(['is_connected' => true]);

    Http::fake(['*/api/v5/publish' => Http::response('', 200)]);

    $response = $this->actingAs($tenant)
        ->postJson('/api/v1/commands/StartService', [
            'bayId' => 'bay_00000001',
            'serviceId' => 'svc_wash',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'sent');

    $this->assertDatabaseHas('command_history', [
        'tenant_id' => $tenant->id,
        'action' => 'StartService',
        'status' => 'sent',
    ]);
});

test('POST /commands/Reset fails when station disconnected', function () { ... });

// ConformanceTest.php
test('GET /conformance returns score and results', function () { ... });
test('POST /conformance/reset clears all results', function () { ... });
test('GET /conformance/export/json returns downloadable file', function () { ... });
```

---

## Factories

```php
// TenantFactory.php
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'password' => Hash::make('password'),
            'protocol_version' => '0.1.0',
            'validation_mode' => 'strict',
            'email_verified_at' => now(),
        ];
    }
}

// TenantStationFactory.php
class TenantStationFactory extends Factory
{
    protected $model = TenantStation::class;

    public function definition(): array
    {
        $index = fake()->unique()->numberBetween(1, 10000);
        return [
            'id' => fake()->uuid(),
            'station_id' => 'stn_' . str_pad(dechex($index), 8, '0', STR_PAD_LEFT),
            'mqtt_username' => 'sandbox_' . bin2hex(random_bytes(8)),
            'mqtt_password_hash' => Hash::make('test-password'),
            'mqtt_password_encrypted' => encrypt('test-password'),
            'protocol_version' => '0.1.0',
            'is_connected' => false,
        ];
    }
}
```

---

## Test Helpers

```php
// tests/TestCase.php
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Flush Redis test DB
        Redis::connection('stream')->flushdb();

        // Disable throttle for tests
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    protected function actingAsTenant(?Tenant $tenant = null): self
    {
        $tenant ??= Tenant::factory()->create();
        return $this->actingAs($tenant);
    }

    protected function makeHandlerContext(string $action, array $payload = [], array $overrides = []): HandlerContext
    {
        return new HandlerContext(
            tenantId: $overrides['tenantId'] ?? 'tenant-uuid',
            stationId: $overrides['stationId'] ?? 'stn_00000001',
            action: $action,
            messageId: $overrides['messageId'] ?? 'msg_' . bin2hex(random_bytes(8)),
            messageType: $overrides['messageType'] ?? 'Request',
            payload: $payload,
            envelope: array_merge([
                'action' => $action,
                'messageType' => 'Request',
                'source' => 'Station',
                'protocolVersion' => '0.1.0',
                'timestamp' => now()->format('Y-m-d\TH:i:s.v\Z'),
                'payload' => $payload,
            ], $overrides['envelope'] ?? []),
            protocolVersion: $overrides['protocolVersion'] ?? '0.1.0',
        );
    }
}
```

---

## CI Expectations

Target: all tests pass in under 60 seconds on CI.

```yaml
# GitHub Actions
- name: Run tests
  run: php vendor/bin/pest --parallel --processes=4
```

Minimum coverage targets (not enforced in v1, but tracked):
- Handlers: 90%+
- Services: 80%+
- Conformance rules: 95%+
- API endpoints: 80%+

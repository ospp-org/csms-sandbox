# CSMS Sandbox — Code Style & Conventions

---

## PHP

### Version & Features

- PHP 8.4+ — use constructor promotion, readonly properties, enums, named arguments, match expressions, fibers (where applicable)
- Strict types in every file: `declare(strict_types=1);`
- No `mixed` type hints unless absolutely necessary

### Naming

| Element | Convention | Example |
|---------|-----------|---------|
| Class | PascalCase | `BootNotificationHandler` |
| Method | camelCase | `handleBoot()` |
| Property | camelCase | `$stationId` |
| Constant | UPPER_SNAKE | `MAX_RETRY_COUNT` |
| Enum case | PascalCase | `BayStatus::Available` |
| Config key | snake_case | `mqtt.webhook.secret` |
| DB column | snake_case | `station_id` |
| Route | kebab-case | `/api/v1/conformance/export/pdf` |
| Blade view | kebab-case | `dashboard.live-monitor` |
| Event | PascalCase | `MessageReceived` |
| Job | PascalCase | `ProcessMqttMessage` |

### Classes

```php
<?php

declare(strict_types=1);

namespace App\Handlers;

// Framework imports first, then app imports, then SDK imports
use Illuminate\Support\Facades\Log;
use App\Services\StationStateService;
use Ospp\Protocol\Actions\OsppAction;

final class BootNotificationHandler
{
    public function __construct(
        private readonly StationStateService $stationState,
        private readonly EmqxApiPublisher $publisher,
    ) {}

    public function __invoke(HandlerContext $context): HandlerResult
    {
        // ...
    }
}
```

Rules:
- `final` by default on all classes (extend only when designed for it)
- `readonly` on all constructor-promoted properties
- Single responsibility — one handler per file, one service per concern
- No abstract classes unless 3+ implementations exist
- No traits (use composition via constructor injection)

### Methods

- Maximum 20 lines per method (extract to private methods)
- Maximum 3 parameters (use DTOs for more)
- Return type on every method
- No `void` return for methods that could return useful data
- Early return pattern (guard clauses first)

```php
public function handle(string $stationId, string $payload): HandlerResult
{
    $station = $this->stationState->get($stationId);
    if ($station === null) {
        return HandlerResult::rejected('Station not registered');
    }

    if ($station->lifecycle !== 'online') {
        return HandlerResult::rejected('Station not online');
    }

    // Happy path
    $response = $this->buildResponse($station);
    return HandlerResult::accepted($response);
}
```

### Error Handling

- Business logic errors: return Result objects (never throw for expected failures)
- Infrastructure errors: let exceptions propagate (Laravel handles logging + retry)
- Never catch `\Throwable` silently — always log or rethrow
- Never use `@` error suppression

```php
// Good — business logic returns result
public function validateMessage(string $payload): ValidationResult
{
    $errors = $this->schema->validate($payload);
    if ($errors !== []) {
        return ValidationResult::invalid($errors);
    }
    return ValidationResult::valid();
}

// Good — infrastructure error propagates
public function publishToEmqx(string $topic, string $payload): void
{
    $response = Http::post($this->apiUrl, [...]);
    if ($response->failed()) {
        throw new EmqxPublishException("Failed to publish: {$response->status()}");
    }
}
```

### DTOs

Use `readonly` classes for data transfer:

```php
final readonly class HandlerContext
{
    public function __construct(
        public string $tenantId,
        public string $stationId,
        public string $action,
        public string $messageId,
        public string $messageType,
        public array $payload,
        public string $rawPayload,
        public string $protocolVersion,
    ) {}
}

final readonly class HandlerResult
{
    private function __construct(
        public string $status,
        public array $responsePayload,
        public ?string $errorCode = null,
        public ?string $errorText = null,
    ) {}

    public static function accepted(array $payload): self
    {
        return new self('Accepted', $payload);
    }

    public static function rejected(string $reason, string $code = '1000'): self
    {
        return new self('Rejected', [], $code, $reason);
    }
}
```

---

## Laravel Patterns

### Controllers

- Single-action controllers where possible (`__invoke`)
- Thin controllers — validate input, call service, return response
- No business logic in controllers
- Form Request classes for validation

```php
final class SendCommandController extends Controller
{
    public function __invoke(
        SendCommandRequest $request,
        CommandService $commandService,
        string $action,
    ): JsonResponse {
        $result = $commandService->send(
            tenantId: $request->user()->id,
            action: $action,
            parameters: $request->validated(),
        );

        return response()->json($result->toArray(), $result->statusCode());
    }
}
```

### Services

- One public method per use case (or closely related group)
- Constructor injection only (no method injection, no service locator)
- No static methods (except factory methods on DTOs)

### Models

- No business logic in models — models are data access only
- Scopes for common queries
- `$casts` for type safety
- No `$guarded = []` — always explicit `$fillable`

```php
final class MessageLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'station_id',
        'direction',
        'action',
        'message_id',
        'message_type',
        'payload',
        'schema_valid',
        'validation_errors',
        'processing_time_ms',
    ];

    protected $casts = [
        'payload' => 'array',
        'validation_errors' => 'array',
        'schema_valid' => 'boolean',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', 'inbound');
    }
}
```

### Routes

API routes:
```php
Route::prefix('api/v1')->group(function () {
    // Public
    Route::post('auth/register', RegisterController::class);
    Route::post('auth/login', LoginController::class);
    Route::post('auth/google', GoogleOAuthController::class);

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('station', [StationController::class, 'show']);
        Route::get('messages', [MessageController::class, 'index']);
        Route::post('commands/{action}', SendCommandController::class);
        Route::get('conformance', [ConformanceController::class, 'index']);
    });
});
```

Internal routes (EMQX only):
```php
Route::prefix('internal')->middleware('verify-emqx')->group(function () {
    Route::post('mqtt/webhook', MqttWebhookController::class);
    Route::post('mqtt/auth', MqttAuthController::class);
});
```

---

## Testing

### Framework

- **Pest** for all tests (not PHPUnit directly)
- Parallel execution: `pest --parallel --processes=N`
- Feature tests for HTTP endpoints and integration
- Unit tests for services, handlers, conformance rules

### Naming

```php
// Feature test
test('POST /api/v1/auth/register creates tenant and returns JWT', function () { ... });
test('BootNotification from registered station returns Accepted', function () { ... });

// Unit test
test('ConformanceScorer calculates correct percentage', function () { ... });
test('HeartbeatTimingRule flags drift above 10%', function () { ... });
```

### Structure

```php
test('description', function () {
    // Arrange
    $tenant = Tenant::factory()->create();
    $station = TenantStation::factory()->for($tenant)->create();

    // Act
    $result = $this->postJson('/api/v1/commands/Reset', [
        'resetType' => 'Soft',
    ]);

    // Assert
    $result->assertStatus(200);
    expect($result->json('status'))->toBe('sent');
});
```

### What to test

| Layer | Test Type | What |
|-------|-----------|------|
| Controllers | Feature | HTTP status, response structure, auth, validation |
| Handlers | Unit | Input → output, state changes, edge cases |
| Services | Unit | Business logic, calculations |
| Conformance Rules | Unit | Each rule with valid/invalid inputs |
| MQTT pipeline | Feature | Webhook → queue → handler → response |

---

## Frontend (Blade + Alpine)

### Alpine.js Conventions

```html
<!-- Component initialization -->
<div x-data="liveMonitor()" x-init="connect()">
    <!-- Reactive display -->
    <template x-for="msg in messages" :key="msg.id">
        <div x-text="msg.action"></div>
    </template>
</div>

<script>
function liveMonitor() {
    return {
        messages: [],
        paused: false,
        connect() {
            Echo.channel('station.' + stationId)
                .listen('MessageReceived', (e) => {
                    if (!this.paused) {
                        this.messages.unshift(e.message);
                        if (this.messages.length > 1000) {
                            this.messages.pop();
                        }
                    }
                });
        },
        togglePause() {
            this.paused = !this.paused;
        }
    };
}
</script>
```

### Tailwind Usage

- Use utility classes directly in Blade (no custom CSS except Tailwind imports)
- Dark mode: not required for v1
- Responsive: sidebar collapses on mobile
- Color scheme: neutral grays + OSPP brand blue (#2563EB)

---

## Git

### Commits

- Conventional commits: `feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `test:`
- No Co-Authored-By or Signed-off-by headers
- Imperative mood: "add handler" not "added handler"
- One logical change per commit

### Branches

- `main` — stable, deployable
- Feature branches for multi-session work (if needed)
- No develop branch — main is always deployable

---

## Docker

### Dockerfile

- Multi-stage: deps → development → production
- Alpine base for small images
- `COPY composer.json composer.lock ./` before `COPY . .` (layer caching)
- `composer install` (not `composer update`) in Dockerfile
- Entrypoint: auto-setup (migrations, JWT keys, permissions)
- PHP-FPM: `pm = static`, configurable max_children

### Docker Compose

- Health checks on all infrastructure services (postgres, redis, emqx)
- `depends_on` with `condition: service_healthy`
- Volumes for persistent data (postgres, redis with appendonly)
- `.env` for all configurable values
- `docker-compose.override.yml` for dev overrides (gitignored)

# CSMS Sandbox — Architecture

---

## System Diagram

```
┌─────────────┐     MQTT (TLS 8883)     ┌──────────┐   async webhook   ┌──────────────┐
│   Station    │◄───────────────────────►│   EMQX   │─────────────────►│  Laravel App  │
│ (firmware)   │                         │  Broker   │                  │   (Nginx)     │
└─────────────┘                         └──────────┘                  └──────┬───────┘
                                              │                              │
                                         ACL check                    ┌──────┴───────┐
                                         (HTTP auth                   │  Queue Worker │
                                          → Laravel)                  │  (queue:work) │
                                                                      └──────┬───────┘
┌─────────────┐     HTTPS + WebSocket    ┌──────────┐                       │
│   Browser    │◄───────────────────────►│  Nginx   │◄──────────────────────┘
│ (Dashboard)  │                         │          │
└─────────────┘                         └──────────┘
                                                                      ┌──────────────┐
                                              ┌──────────────┐        │    Redis 7    │
                                              │ PostgreSQL 16│        │ Queue + Cache │
                                              │  + Reverb    │        │ + Station State│
                                              └──────────────┘        └──────────────┘
```

---

## Message Flow

### Station → CSMS (inbound)

```
1. Station publishes MQTT message
   → ospp/v1/stations/{station_id}/to-server

2. EMQX ACL: verify mqtt_username owns station_id
   → HTTP auth request to Laravel /internal/mqtt/auth
   → Allow or Deny

3. EMQX rule engine: forward payload to webhook (async)
   → POST /internal/mqtt/webhook

4. MqttWebhookController (thin proxy, ~2ms):
   → Extract stationId from topic
   → Dispatch ProcessMqttMessage job to Laravel Queue
   → Return 200 to EMQX

5. Queue worker picks up job:
   → Log raw message to message_log (direction: inbound)
   → Validate payload against JSON Schema (ospp/protocol SDK)
   → Store validation result in message_log
   → Route to appropriate handler via MqttMessageDispatcher

6. Handler processes message:
   → Update in-memory station state (Redis)
   → Generate response payload
   → Publish response to EMQX API
     → ospp/v1/stations/{station_id}/to-station
   → Log response to message_log (direction: outbound)
   → Update conformance_results
   → Broadcast to Reverb channel (dashboard WebSocket)
```

### CSMS → Station (outbound, from dashboard)

```
1. Firmware dev clicks "Send Command" in dashboard
   → POST /api/v1/commands/{action}

2. Controller:
   → Validate command parameters against JSON Schema
   → Build OSPP message envelope
   → Publish to EMQX API
     → ospp/v1/stations/{station_id}/to-station
   → Log to message_log (direction: outbound)
   → Store in command_history (status: sent)
   → Start 30s timeout timer (Redis)

3. Station receives command, processes, sends response
   → Normal inbound flow (steps 1-6 above)
   → Handler detects it's a command response
   → Update command_history (status: responded)
   → Cancel timeout timer
```

---

## Folder Structure

```
csms-sandbox/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── MessagesCleanupCommand.php
│   │       └── StationCheckConnectionCommand.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── RegisterController.php
│   │   │   │   ├── LoginController.php
│   │   │   │   ├── GoogleOAuthController.php
│   │   │   │   └── LogoutController.php
│   │   │   │
│   │   │   ├── Dashboard/
│   │   │   │   ├── SetupController.php
│   │   │   │   ├── LiveMonitorController.php
│   │   │   │   ├── CommandCenterController.php
│   │   │   │   ├── ConformanceController.php
│   │   │   │   ├── HistoryController.php
│   │   │   │   └── SettingsController.php
│   │   │   │
│   │   │   ├── Api/
│   │   │   │   ├── StationController.php
│   │   │   │   ├── MessageController.php
│   │   │   │   ├── CommandController.php
│   │   │   │   ├── ConformanceController.php
│   │   │   │   └── SettingsController.php
│   │   │   │
│   │   │   └── Internal/
│   │   │       ├── MqttWebhookController.php
│   │   │       └── MqttAuthController.php
│   │   │
│   │   ├── Middleware/
│   │   │   ├── VerifyEmqxWebhook.php
│   │   │   └── EnsureStationConnected.php
│   │   │
│   │   └── Requests/
│   │       ├── Auth/
│   │       ├── Command/
│   │       └── Settings/
│   │
│   ├── Jobs/
│   │   └── ProcessMqttMessage.php
│   │
│   ├── Models/
│   │   ├── Tenant.php
│   │   ├── TenantStation.php
│   │   ├── MessageLog.php
│   │   ├── ConformanceResult.php
│   │   └── CommandHistory.php
│   │
│   ├── Mqtt/
│   │   ├── MqttMessageDispatcher.php
│   │   ├── EmqxApiPublisher.php
│   │   └── TopicResolver.php
│   │
│   ├── Handlers/
│   │   ├── BootNotificationHandler.php
│   │   ├── HeartbeatHandler.php
│   │   ├── StatusNotificationHandler.php
│   │   ├── MeterValuesHandler.php
│   │   ├── StartServiceResponseHandler.php
│   │   ├── StopServiceResponseHandler.php
│   │   ├── ReserveBayResponseHandler.php
│   │   ├── CancelReservationResponseHandler.php
│   │   ├── DataTransferHandler.php
│   │   ├── SecurityEventHandler.php
│   │   ├── SignCertificateHandler.php
│   │   ├── ChangeConfigurationResponseHandler.php
│   │   ├── GetConfigurationResponseHandler.php
│   │   ├── ResetResponseHandler.php
│   │   ├── UpdateFirmwareResponseHandler.php
│   │   ├── UploadDiagnosticsResponseHandler.php
│   │   ├── SetMaintenanceModeResponseHandler.php
│   │   ├── TriggerMessageResponseHandler.php
│   │   ├── UpdateServiceCatalogResponseHandler.php
│   │   ├── CertificateInstallResponseHandler.php
│   │   └── TriggerCertificateRenewalResponseHandler.php
│   │
│   ├── Services/
│   │   ├── TenantService.php
│   │   ├── StationStateService.php
│   │   ├── MessageLogService.php
│   │   ├── ConformanceService.php
│   │   ├── SchemaValidationService.php
│   │   ├── BehaviorValidationService.php
│   │   ├── CommandService.php
│   │   └── MqttCredentialService.php
│   │
│   ├── Events/
│   │   ├── MessageReceived.php
│   │   ├── MessageSent.php
│   │   ├── StationConnected.php
│   │   └── StationDisconnected.php
│   │
│   └── Conformance/
│       ├── Rules/
│       │   ├── BootFirstRule.php
│       │   ├── HeartbeatTimingRule.php
│       │   ├── SessionStateRule.php
│       │   ├── BayTransitionRule.php
│       │   ├── ResponseTimingRule.php
│       │   └── IdempotencyRule.php
│       ├── ConformanceScorer.php
│       └── ReportExporter.php
│
├── config/
│   ├── mqtt.php
│   ├── conformance.php
│   └── sandbox.php
│
├── database/
│   ├── migrations/
│   └── seeders/
│
├── docker/
│   ├── emqx/
│   │   ├── emqx.conf
│   │   ├── acl.conf
│   │   └── init-webhook.sh
│   ├── nginx/
│   │   └── default.conf
│   ├── php/
│   │   ├── php-dev.ini
│   │   └── www.conf
│   ├── supervisor/
│   │   └── supervisord.conf
│   └── entrypoint.sh
│
├── docs/
│   ├── PRD-CSMS-Sandbox.md
│   ├── BRIEF.md
│   ├── ARCHITECTURE.md
│   ├── DATABASE.md
│   ├── MQTT.md
│   ├── API.md
│   ├── HANDLERS.md
│   ├── CONFORMANCE.md
│   ├── FRONTEND.md
│   ├── TESTING.md
│   ├── DEPLOYMENT.md
│   └── STYLE.md
│
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   └── app.blade.php
│   │   ├── auth/
│   │   │   ├── register.blade.php
│   │   │   └── login.blade.php
│   │   ├── dashboard/
│   │   │   ├── setup.blade.php
│   │   │   ├── monitor.blade.php
│   │   │   ├── commands.blade.php
│   │   │   ├── conformance.blade.php
│   │   │   ├── history.blade.php
│   │   │   └── settings.blade.php
│   │   └── components/
│   │       ├── message-row.blade.php
│   │       ├── conformance-badge.blade.php
│   │       ├── connection-status.blade.php
│   │       └── command-form.blade.php
│   │
│   └── css/
│       └── app.css (Tailwind imports only)
│
├── routes/
│   ├── web.php
│   ├── api.php
│   └── channels.php
│
├── tests/
│   ├── Feature/
│   │   ├── Auth/
│   │   ├── Mqtt/
│   │   ├── Handlers/
│   │   ├── Conformance/
│   │   └── Api/
│   └── Unit/
│       ├── Handlers/
│       ├── Services/
│       └── Conformance/
│
├── docker-compose.yml
├── docker-compose.override.yml.example
├── Dockerfile
├── .env.example
├── .gitignore
├── .gitattributes
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── README.md
└── LICENSE
```

---

## Module Boundaries

### Mqtt/ — Message Transport

Receives raw MQTT messages via webhook, dispatches to queue. Publishes responses to EMQX. Resolves topics to stationId. Zero business logic.

### Handlers/ — Protocol Logic

One handler per OSPP action. Receives parsed message, processes per protocol spec, returns response. Each handler:
- Accepts typed DTO (from SDK)
- Updates station state (via StationStateService → Redis)
- Generates response DTO
- Returns response (publishing handled by dispatcher)

Handlers do NOT:
- Access database directly (logging done by dispatcher)
- Know about tenants (multi-tenancy is transparent)
- Publish to EMQX (dispatcher does it)

### Services/ — Business Logic

- **TenantService** — CRUD, provisioning
- **StationStateService** — Redis-backed station state (bays, sessions, config)
- **MessageLogService** — writes to message_log table
- **ConformanceService** — updates conformance_results, calculates scores
- **SchemaValidationService** — validates payloads against JSON Schema from SDK
- **BehaviorValidationService** — checks protocol behavior rules
- **CommandService** — builds outbound commands, tracks pending, handles timeouts
- **MqttCredentialService** — generates/validates MQTT credentials

### Conformance/ — Protocol Conformance

- **Rules/** — individual behavior checks (boot first, heartbeat timing, etc.)
- **ConformanceScorer** — aggregates results, calculates scores
- **ReportExporter** — generates PDF and JSON conformance reports

### Events/ — Real-time Broadcasting

Laravel events dispatched after message processing. Listened by Reverb for WebSocket broadcasting to dashboard.

---

## Station State (Redis)

Station state lives in Redis, NOT PostgreSQL. It's ephemeral — reflects current station status, not historical data.

```
Key: sandbox:station:{station_id}:state
Type: Hash
Fields:
  lifecycle: offline|booting|online
  firmware_version: "1.0.0"
  station_model: "WashPro 5000"
  station_vendor: "CSMS Dev"
  heartbeat_interval: 30
  last_heartbeat: 1709985600 (unix timestamp)
  protocol_version: "0.1.0"

Key: sandbox:station:{station_id}:bay:{bay_number}
Type: Hash
Fields:
  status: unknown|available|reserved|occupied|finishing|faulted|unavailable
  session_id: "sess_..." (nullable)
  reservation_id: "rsv_..." (nullable)
  service_id: "svc_..." (nullable)

Key: sandbox:station:{station_id}:config
Type: Hash
Fields:
  (key-value pairs from ChangeConfiguration)

Key: sandbox:station:{station_id}:connected
Type: String
Value: "1"
TTL: 90s (refreshed by each heartbeat)
```

---

## Docker Services

| Service | Image | Ports | Depends On |
|---------|-------|-------|-----------|
| app | Custom (Laravel 12) | — (internal 9000) | postgres, redis, emqx |
| nginx | nginx:alpine | 80, 443 | app |
| postgres | postgres:16-alpine | 5432 (internal) | — |
| redis | redis:7-alpine | 6379 (internal) | — |
| emqx | emqx/emqx:5.8 | 1883, 8883, 18083 | — |
| queue-worker | Same as app | — | redis, postgres, emqx, app |
| reverb | Same as app | 8080 (internal) | redis |
| scheduler | Same as app | — | postgres, redis |
| emqx-init | curl:alpine | — | emqx |

**queue-worker:** `php artisan queue:work redis --queue=mqtt-messages --sleep=3 --tries=3 --memory=128 --timeout=60`

**reverb:** `php artisan reverb:start --host=0.0.0.0 --port=8080`

**scheduler:** `php artisan schedule:work`

---

## Key Design Decisions

### Why Redis for station state (not PostgreSQL)?

Station state changes on every message (heartbeat, status notification, meter values). That's 1-10 writes/second per station. Redis handles this trivially. PostgreSQL would create write amplification (WAL, indexes, MVCC) for ephemeral data that's worthless after station disconnects.

PostgreSQL stores: tenants, message history, conformance results — data that must survive restarts and is queried with complex filters.

### Why Laravel Queue (not Redis Streams)?

Laravel Queue (`queue:work` with Redis Lists) is battle-tested by millions of applications. BRPOP is atomic, retry is built-in, dead letter handling is built-in. Redis Streams requires a custom consumer that must handle consumer groups, XACK, pending message recovery, reconnection — all of which Laravel Queue handles natively.

### Why Blade + Alpine (not React/Vue)?

Dashboard is server-rendered with sprinkles of reactivity. No build process, no node_modules, no webpack. Alpine.js handles toggles, filters, dropdowns. Laravel Reverb + Echo handle real-time updates. Total frontend JS: ~200 lines per page.

### Why EMQX HTTP auth (not built-in auth)?

EMQX HTTP auth backend makes an HTTP request to Laravel for every MQTT connect/publish/subscribe. This lets Laravel control ACL dynamically — when a tenant is deleted, their MQTT access is revoked immediately without reloading EMQX config.

### Why one station per tenant?

Sandbox tests protocol compliance, not scalability. One station = one MQTT connection = simple state management. Multi-station load testing is a different tool (station-simulator).

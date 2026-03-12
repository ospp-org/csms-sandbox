# PRD: OSPP CSMS Sandbox

## Product Requirements Document

**Version:** 1.0  
**Date:** March 2026  
**Repository:** `ospp-org/csms-sandbox`  
**License:** MIT (open source)  
**Domain:** `csms-sandbox.ospp-standard.org`

---

## 1. Overview

### What

A hosted, multi-tenant OSPP protocol testing environment. Firmware developers connect their station (physical or simulated) to a cloud CSMS that validates every MQTT message against the OSPP specification, provides a real-time message inspector, manual command sender, and generates a conformance report with scoring.

### For Whom

Firmware developers implementing the OSPP protocol on microcontrollers (ESP32, STM32, or similar). They write C/C++ code on the station side and need a compliant CSMS to test against — without installing Docker, PHP, PostgreSQL, or any server infrastructure.

### Problem Solved

Today, a firmware developer who wants to test their OSPP implementation must:

1. Clone `csms-server` (proprietary, not available)
2. Install Docker, PHP 8.3, PostgreSQL, Redis, EMQX
3. Run `docker compose up` and configure everything
4. Debug protocol issues with no visibility into what went wrong

With CSMS Sandbox:

1. Open browser → `csms-sandbox.ospp-standard.org`
2. Register (30 seconds)
3. Copy MQTT credentials into firmware
4. Connect — see every message, every validation error, every conformance gap

### Success Metrics

- Firmware developer goes from zero to first successful BootNotification in under 5 minutes
- Conformance report identifies all protocol violations with actionable error messages
- Zero infrastructure required on the firmware developer's side

---

## 2. User Journey

### 2.1 Registration

1. Firmware developer opens `csms-sandbox.ospp-standard.org`
2. Registers via:
   - **Email + password** (with email verification)
   - **Google OAuth** (one-click)
3. Receives confirmation, redirected to dashboard

### 2.2 Station Setup

1. Dashboard shows **Setup** page with:
   - MQTT host: `csms-sandbox.ospp-standard.org`
   - MQTT port: `8883` (TLS) / `1883` (plain, dev only)
   - MQTT username: auto-generated (unique per tenant)
   - MQTT password: auto-generated (shown once, regeneratable)
   - Station ID: auto-assigned `stn_` + hex format per OSPP spec
   - Protocol version: selectable dropdown (default: `0.1.0`)
2. Code snippets provided for: C (ESP-IDF), Python (paho-mqtt), JavaScript (mqtt.js)
3. Connection status indicator: 🔴 Disconnected / 🟢 Connected

### 2.3 Development Loop

1. Firmware developer powers on device / runs firmware
2. Device connects to MQTT broker with provided credentials
3. Device sends `BootNotification`
4. **Dashboard shows instantly:**
   - Message received (inbound, raw payload)
   - Schema validation result (pass/fail with details)
   - CSMS response sent (outbound, raw payload)
5. Firmware developer iterates:
   - Sees validation errors → fixes firmware → reconnects
   - Tests each message type one by one
   - Sends commands from dashboard to test response handling

### 2.4 Conformance Testing

1. Firmware developer opens **Conformance** tab
2. Sees 26-message checklist with status per message:
   - ✅ Passed — message sent and validated correctly
   - ❌ Failed — message sent but had validation errors
   - ⚪ Not tested — message never sent
3. Clicks on any message for details:
   - Last payload sent
   - Validation errors (with JSON path, expected vs actual)
   - Expected behavior per OSPP spec
4. Exports report as PDF or JSON

---

## 3. Core Features

### 3.1 Multi-Tenant MQTT Isolation

Each tenant receives unique MQTT credentials. EMQX ACL restricts each tenant to their assigned station ID only. Topics follow the standard OSPP format — firmware code is identical to production:

```
ospp/v1/stations/{station_id}/to-server    (station → CSMS)
ospp/v1/stations/{station_id}/to-station   (CSMS → station)
```

The firmware developer's code does not know it is connecting to a sandbox. Topic structure, message format, and protocol behavior are identical to a production CSMS.

### 3.2 Protocol Handlers (26/26 OSPP Messages)

The sandbox implements all 26 OSPP v0.1.0 messages with correct protocol behavior:

**Station → CSMS (inbound, sandbox responds):**

| # | Message | Sandbox Behavior |
|---|---------|-----------------|
| 1 | BootNotification | Accept if station registered, return serverTime + heartbeatInterval |
| 2 | Heartbeat | Respond with serverTime |
| 3 | StatusNotification | Acknowledge, update bay status in-memory |
| 4 | MeterValues | Acknowledge, log readings |
| 5 | StartServiceResponse | Process Accepted/Rejected, update session state |
| 6 | StopServiceResponse | Process, transition bay Occupied → Finishing → Available |
| 7 | ReserveBayResponse | Process, update reservation state |
| 8 | CancelReservationResponse | Process, release bay |
| 9 | DataTransfer | Echo back with Accepted |
| 10 | SecurityEvent | Acknowledge, log event |
| 11 | SignCertificate | Respond with signed certificate |
| 12 | ChangeConfigurationResponse | Process |
| 13 | GetConfigurationResponse | Process, log returned config |
| 14 | ResetResponse | Process, expect reboot cycle |
| 15 | UpdateFirmwareResponse | Process status updates |
| 16 | UploadDiagnosticsResponse | Process |
| 17 | SetMaintenanceModeResponse | Process |
| 18 | TriggerMessageResponse | Process |
| 19 | UpdateServiceCatalogResponse | Process |
| 20 | CertificateInstallResponse | Process |
| 21 | TriggerCertificateRenewalResponse | Process |

**CSMS → Station (outbound, triggered from dashboard):**

| # | Command | Parameters |
|---|---------|-----------|
| 22 | StartService | bayId, serviceId, sessionId |
| 23 | StopService | bayId, sessionId |
| 24 | ReserveBay | bayId, reservationId, ttlMinutes |
| 25 | CancelReservation | bayId, reservationId |
| 26 | ChangeConfiguration | keys (key-value pairs) |
| 27 | GetConfiguration | requestedKeys (optional) |
| 28 | Reset | resetType (Soft/Hard) |
| 29 | UpdateFirmware | firmwareUrl, version |
| 30 | UploadDiagnostics | uploadUrl |
| 31 | SetMaintenanceMode | enabled, bayId (optional) |
| 32 | TriggerMessage | requestedMessage, bayId (optional) |
| 33 | UpdateServiceCatalog | services array |
| 34 | CertificateInstall | certificateType, certificate |
| 35 | TriggerCertificateRenewal | certificateType |

### 3.3 Validation Modes

**Strict Mode** (default for conformance testing):
- Every inbound message validated against JSON Schema from `ospp/protocol` SDK
- Invalid messages are rejected — station receives error response with details
- Validation errors logged and visible in dashboard
- Conformance report reflects strict validation

**Lenient Mode** (for iterative development):
- Every inbound message validated but processed regardless of errors
- Station receives normal response even if payload has issues
- Validation warnings shown in dashboard (yellow, not red)
- Firmware developer can iterate without being blocked by validation errors

Configurable per tenant in dashboard settings.

### 3.4 Live Message Inspector

Real-time WebSocket feed showing:

- **Timestamp** — millisecond precision
- **Direction** — ⬆️ Inbound (station → CSMS) / ⬇️ Outbound (CSMS → station)
- **Action** — BootNotification, Heartbeat, StartService, etc.
- **Message ID** — OSPP messageId for correlation
- **Payload** — expandable JSON with syntax highlighting
- **Validation** — ✅ Valid / ❌ Invalid (with error details inline)

Filters:
- By action (dropdown)
- By direction (inbound/outbound/both)
- By validation status (all/valid/invalid)
- By time range

Auto-scroll with pause button. Maximum 1000 messages in browser, older messages in History tab.

### 3.5 Command Center

Form-based UI for sending commands to the connected station:

- Dropdown: select command (StartService, Reset, etc.)
- Form fields auto-generated from OSPP schema:
  - Required fields marked with asterisk
  - Default values pre-filled where applicable
  - Enum fields as dropdowns
  - JSON fields with syntax-highlighted editor
- **Send** button → publishes to MQTT → response visible in Message Inspector
- **History** of sent commands with responses

### 3.6 Conformance Scoring

#### Checklist (26 messages)

Each message has a status:

| Status | Meaning |
|--------|---------|
| ✅ Passed | Message sent, schema valid, behavior correct |
| ❌ Failed | Message sent but schema invalid or behavior incorrect |
| ⚠️ Partial | Message sent, schema valid, but behavior has warnings |
| ⚪ Not tested | Message never sent by station |

#### Scoring

`{passed} / {total_tested}` — e.g., `18/22` (4 not tested)

#### Per-message detail

For each failed/partial message:
- **What was sent** — raw payload
- **What was expected** — per OSPP spec (schema + behavior rules)
- **What went wrong** — JSON path to error, expected vs actual value
- **How to fix** — actionable guidance

#### Behavior validation (beyond schema)

| Rule | What it checks |
|------|---------------|
| Heartbeat timing | Station sends heartbeat within ±10% of configured interval |
| Boot sequence | BootNotification is first message after connect |
| State machine | No MeterValues without active session |
| Response timing | Station responds to commands within 30 seconds |
| Bay lifecycle | StatusNotification reflects correct bay transitions |
| Idempotency | Duplicate messages handled correctly |

#### Export

- **PDF** — branded OSPP conformance report, suitable for documentation
- **JSON** — machine-readable, for CI/CD integration

### 3.7 Message History

Persistent storage of all messages per tenant:

- Paginated table (50 per page)
- Filterable by action, direction, validation status, time range
- Searchable by messageId, stationId, payload content
- Exportable as CSV or JSON
- Retention: 30 days

### 3.8 Protocol Version Selection

Tenant can select which OSPP protocol version to test against:

- Default: `0.1.0` (current)
- Future versions added as dropdown options
- Schema validation uses version-specific schemas from SDK
- Conformance checklist adapts to version (new messages in newer versions)

Changing version clears conformance results (different spec = different validation).

---

## 4. Architecture

### 4.1 System Diagram

```
┌─────────────┐     MQTT (TLS)     ┌──────────┐    webhook    ┌──────────────┐
│   Station    │◄──────────────────►│   EMQX   │─────────────►│  Laravel App  │
│ (firmware)   │                    │  Broker   │              │  (API + Queue)│
└─────────────┘                    └──────────┘              └──────┬───────┘
                                                                    │
┌─────────────┐     WebSocket      ┌──────────┐                    │
│   Browser    │◄─────────────────►│  Nginx   │◄───────────────────┘
│ (Dashboard)  │     REST API      │          │
└─────────────┘                    └──────────┘
                                                              ┌──────────────┐
                                                              │  PostgreSQL  │
                                                              │  + Redis     │
                                                              └──────────────┘
```

### 4.2 Stack

| Component | Technology | Purpose |
|-----------|-----------|---------|
| Backend | Laravel 11, PHP 8.3+ | REST API, queue workers, WebSocket |
| MQTT Broker | EMQX 5.8 | Station MQTT connections, ACL per tenant |
| Database | PostgreSQL 16 | Tenants, message logs, conformance results |
| Cache/Queue | Redis 7 | Laravel Queue (Redis Lists), session cache |
| Web Server | Nginx | Reverse proxy, static assets, WebSocket upgrade |
| Frontend | Blade + Alpine.js + Tailwind | Dashboard SPA-like experience |
| SDK | `ospp/protocol` ^0.2.1 | JSON Schemas, DTOs, enums, validation |
| Containerization | Docker + Docker Compose | Development and deployment |

### 4.3 MQTT Message Flow

```
Station publishes to: ospp/v1/stations/{station_id}/to-server
    ↓
EMQX ACL: verify station_id belongs to authenticated tenant
    ↓
EMQX rule engine: forward to webhook (async)
    ↓
MqttWebhookController: validate, dispatch ProcessMqttMessage job
    ↓
Laravel Queue worker: 
    1. Log message to DB (message_log)
    2. Validate against JSON Schema
    3. Route to handler
    4. Handler processes + generates response
    5. Publish response to: ospp/v1/stations/{station_id}/to-station
    6. Log response to DB
    7. Update conformance_results
    8. Broadcast to WebSocket (dashboard)
```

### 4.4 Multi-Tenant MQTT (EMQX ACL)

EMQX built-in authentication + authorization:

**Authentication:** Username/password per tenant (stored in EMQX built-in DB or PostgreSQL via HTTP auth).

**Authorization (ACL):**

```
# Tenant can publish to their station's to-server topic
{allow, {username, "{mqtt_username}"}, publish, ["ospp/v1/stations/{station_id}/to-server"]}.

# Tenant can subscribe to their station's to-station topic
{allow, {username, "{mqtt_username}"}, subscribe, ["ospp/v1/stations/{station_id}/to-station"]}.

# Deny everything else
{deny, all}.
```

This ensures complete isolation — tenant A cannot see tenant B's messages, even if they know the station ID.

**LWT (Last Will and Testament):**
Station configures LWT on connect. EMQX publishes LWT when station disconnects unexpectedly. Sandbox detects disconnect and updates dashboard status.

---

## 5. Data Model

### 5.1 Tables

#### tenants

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| email | VARCHAR(255) | Unique, verified |
| name | VARCHAR(255) | Display name |
| password_hash | VARCHAR(255) | bcrypt (null if OAuth) |
| google_id | VARCHAR(255) | Google OAuth ID (nullable) |
| protocol_version | VARCHAR(10) | Selected version, default '0.1.0' |
| validation_mode | ENUM('strict','lenient') | Default 'strict' |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

#### tenant_stations

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| tenant_id | UUID | FK → tenants |
| station_id | VARCHAR(50) | OSPP format: stn_[a-f0-9]{8,} |
| mqtt_username | VARCHAR(100) | Unique, auto-generated |
| mqtt_password_hash | VARCHAR(255) | bcrypt |
| mqtt_password_plain | VARCHAR(255) | Shown once at creation (encrypted at rest) |
| protocol_version | VARCHAR(10) | Inherits from tenant, overrideable |
| is_connected | BOOLEAN | Updated by EMQX hooks |
| last_connected_at | TIMESTAMP | |
| last_boot_at | TIMESTAMP | |
| bay_count | INTEGER | From BootNotification |
| firmware_version | VARCHAR(50) | From BootNotification |
| station_model | VARCHAR(100) | From BootNotification |
| station_vendor | VARCHAR(100) | From BootNotification |
| created_at | TIMESTAMP | |

#### message_log

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Auto-increment (high volume) |
| tenant_id | UUID | FK → tenants |
| station_id | VARCHAR(50) | OSPP station ID |
| direction | ENUM('inbound','outbound') | Station→CSMS or CSMS→Station |
| action | VARCHAR(50) | BootNotification, Heartbeat, etc. |
| message_id | VARCHAR(100) | OSPP messageId |
| message_type | VARCHAR(20) | Request, Response, Event |
| payload | JSONB | Full OSPP message payload |
| schema_valid | BOOLEAN | JSON Schema validation result |
| validation_errors | JSONB | Array of error objects (nullable) |
| processing_time_ms | INTEGER | Time to process in ms |
| created_at | TIMESTAMP | Indexed for queries |

Indexes: `(tenant_id, created_at)`, `(tenant_id, action)`, `(station_id, message_id)`

Retention: messages older than 30 days auto-deleted by scheduled job.

#### conformance_results

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| tenant_id | UUID | FK → tenants |
| protocol_version | VARCHAR(10) | Version tested against |
| action | VARCHAR(50) | OSPP message action |
| status | ENUM('passed','failed','partial','not_tested') | |
| last_tested_at | TIMESTAMP | |
| last_payload | JSONB | Last message payload for this action |
| error_details | JSONB | Array of conformance errors |
| behavior_checks | JSONB | Results of behavior validation |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

Unique constraint: `(tenant_id, protocol_version, action)`

#### command_history

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| tenant_id | UUID | FK → tenants |
| station_id | VARCHAR(50) | |
| action | VARCHAR(50) | Command sent |
| payload | JSONB | Command payload |
| response_payload | JSONB | Station response (nullable) |
| response_received_at | TIMESTAMP | (nullable) |
| status | ENUM('sent','responded','timeout') | |
| created_at | TIMESTAMP | |

### 5.2 Redis Keys

| Key Pattern | Purpose | TTL |
|-------------|---------|-----|
| `sandbox:session:{tenant_id}` | JWT session | 24h |
| `sandbox:station:{station_id}:state` | In-memory station state (bays, sessions) | Persistent |
| `sandbox:station:{station_id}:connected` | Connection status flag | 5min (refreshed by heartbeat) |
| `sandbox:rate:{tenant_id}:api` | API rate limit counter | 1min |
| `sandbox:rate:{tenant_id}:mqtt` | MQTT rate limit counter | 1min |

---

## 6. API Endpoints

### 6.1 Authentication

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v1/auth/register` | Create tenant (email+password or Google OAuth) |
| POST | `/api/v1/auth/login` | Login, return JWT |
| POST | `/api/v1/auth/google` | Google OAuth callback |
| POST | `/api/v1/auth/logout` | Invalidate JWT |
| GET | `/api/v1/auth/me` | Current tenant info |

### 6.2 Station

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/station` | Get station info + MQTT credentials |
| POST | `/api/v1/station/regenerate-password` | Generate new MQTT password |
| GET | `/api/v1/station/status` | Connection status, last boot, firmware info |

### 6.3 Messages

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/messages` | Message history (paginated, filterable) |
| GET | `/api/v1/messages/{id}` | Single message detail |
| GET | `/api/v1/messages/stream` | WebSocket endpoint for live feed |

Query params for list: `action`, `direction`, `schema_valid`, `from`, `to`, `page`, `per_page`

### 6.4 Commands

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v1/commands/{action}` | Send command to station |
| GET | `/api/v1/commands/history` | Command history with responses |
| GET | `/api/v1/commands/{action}/schema` | Get JSON Schema for command parameters |

### 6.5 Conformance

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/conformance` | Full conformance report |
| GET | `/api/v1/conformance/{action}` | Per-action conformance detail |
| POST | `/api/v1/conformance/reset` | Reset all conformance results |
| GET | `/api/v1/conformance/export/pdf` | Export as PDF |
| GET | `/api/v1/conformance/export/json` | Export as JSON |

### 6.6 Settings

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/settings` | Tenant settings |
| PATCH | `/api/v1/settings` | Update settings (validation_mode, protocol_version) |

---

## 7. Dashboard UI

### 7.1 Pages

#### Setup Page

```
┌──────────────────────────────────────────────┐
│  MQTT Connection Details                      │
│                                              │
│  Host: csms-sandbox.ospp-standard.org        │
│  Port: 8883 (TLS) / 1883 (plain)            │
│  Username: sandbox_a1b2c3d4                  │
│  Password: ●●●●●●●● [Show] [Regenerate]     │
│  Station ID: stn_00000001                     │
│  Protocol: [0.1.0 ▾]                         │
│                                              │
│  Status: 🔴 Disconnected                     │
│                                              │
│  ┌─ Code Snippets ──────────────────────┐    │
│  │ [C (ESP-IDF)] [Python] [JavaScript]  │    │
│  │                                      │    │
│  │ #include "mqtt_client.h"             │    │
│  │ esp_mqtt_client_config_t cfg = {     │    │
│  │   .broker.address.uri = "mqtts://    │    │
│  │     csms-sandbox.ospp-standard.org", │    │
│  │   .broker.address.port = 8883,       │    │
│  │   .credentials.username =            │    │
│  │     "sandbox_a1b2c3d4",              │    │
│  │   .credentials.authentication.       │    │
│  │     password = "your_password",      │    │
│  │ };                                   │    │
│  └──────────────────────────────────────┘    │
└──────────────────────────────────────────────┘
```

#### Live Monitor Page

```
┌──────────────────────────────────────────────┐
│  Live Messages          [Pause] [Clear]      │
│  Filter: [All actions ▾] [Both ▾] [All ▾]   │
│                                              │
│  10:23:45.123 ⬆️ BootNotification     ✅     │
│  ├─ messageId: msg_abc123                    │
│  └─ payload: { stationModel: "WashPro"... } │
│                                              │
│  10:23:45.234 ⬇️ BootNotification     ✅     │
│  ├─ messageId: msg_abc123 (response)         │
│  └─ payload: { status: "Accepted"... }       │
│                                              │
│  10:23:46.100 ⬆️ StatusNotification   ❌     │
│  ├─ messageId: msg_def456                    │
│  ├─ payload: { bayId: "invalid"... }         │
│  └─ errors:                                  │
│     • /payload/bayId: must match pattern     │
│       ^bay_[a-f0-9]{8,}$                     │
└──────────────────────────────────────────────┘
```

#### Command Center Page

```
┌──────────────────────────────────────────────┐
│  Send Command                                │
│                                              │
│  Command: [StartService ▾]                   │
│                                              │
│  bayId*:     [bay_00000001        ]          │
│  serviceId*: [svc_wash_basic      ]          │
│  sessionId:  [auto-generated      ]          │
│                                              │
│  [Send Command]                              │
│                                              │
│  ─── Recent Commands ───                     │
│  10:30:12 StartService → Accepted (230ms)    │
│  10:28:45 GetConfiguration → 3 keys (180ms)  │
│  10:25:00 Reset (Soft) → Accepted (2.1s)     │
└──────────────────────────────────────────────┘
```

#### Conformance Page

```
┌──────────────────────────────────────────────┐
│  Conformance Report    Score: 18/22 (81.8%)  │
│  Protocol: OSPP v0.1.0                       │
│  [Export PDF] [Export JSON] [Reset]           │
│                                              │
│  ── Station → CSMS ──                        │
│  ✅ BootNotification         tested 10:23    │
│  ✅ Heartbeat                tested 10:24    │
│  ✅ StatusNotification       tested 10:23    │
│  ❌ MeterValues              tested 10:31    │
│     └─ /payload/readings[0]/value: must be   │
│        number, got string                    │
│  ⚪ DataTransfer             not tested       │
│  ⚪ SecurityEvent            not tested       │
│                                              │
│  ── CSMS → Station ──                        │
│  ✅ StartServiceResponse     tested 10:30    │
│  ✅ StopServiceResponse      tested 10:32    │
│  ❌ ResetResponse            tested 10:35    │
│     └─ Response timeout: station did not     │
│        respond within 30 seconds             │
│  ⚪ UpdateFirmwareResponse   not tested       │
└──────────────────────────────────────────────┘
```

#### History Page

```
┌──────────────────────────────────────────────┐
│  Message History                             │
│  [Action ▾] [Direction ▾] [Valid ▾]          │
│  From: [2026-03-01] To: [2026-03-09]         │
│  Search: [________________] [🔍]             │
│                                              │
│  ┌──────┬─────┬──────────────┬─────┬───────┐ │
│  │ Time │ Dir │ Action       │Valid│ MsgID │ │
│  ├──────┼─────┼──────────────┼─────┼───────┤ │
│  │10:35 │ ⬆️  │ Reset        │ ✅  │ msg.. │ │
│  │10:32 │ ⬇️  │ StopService  │ ✅  │ msg.. │ │
│  │10:32 │ ⬆️  │ StopService  │ ✅  │ msg.. │ │
│  │10:31 │ ⬆️  │ MeterValues  │ ❌  │ msg.. │ │
│  └──────┴─────┴──────────────┴─────┴───────┘ │
│  Page 1 of 12  [< Prev] [Next >]             │
│                                              │
│  [Export CSV] [Export JSON]                   │
└──────────────────────────────────────────────┘
```

### 7.2 Tech Stack (Frontend)

- **Blade templates** — server-rendered pages
- **Alpine.js** — reactive UI without build step
- **Tailwind CSS** — utility-first styling
- **Chart.js** — conformance score visualization
- **WebSocket** — live message feed (Laravel Echo + Pusher protocol via Reverb/Soketi)

No React, no Vue, no npm build. Blade + Alpine + Tailwind via CDN = zero frontend build process.

---

## 8. Conformance Engine

### 8.1 Schema Validation

Every inbound message validated against the JSON Schema from `ospp/protocol` SDK:

```php
$validator = new SchemaValidator();
$result = $validator->validate($payload, $action, $protocolVersion);
// Returns: valid (bool), errors (array of {path, message, expected, actual})
```

Schemas are versioned — `ospp/protocol` SDK provides schemas per protocol version.

### 8.2 Behavior Validation

Beyond schema, the conformance engine checks protocol behavior:

| Rule | Check | How |
|------|-------|-----|
| Boot first | BootNotification must be first message after connect | Track message sequence per connection |
| Heartbeat timing | Heartbeat sent within ±10% of configured interval | Compare timestamps, flag drift > 10% |
| Session state | No MeterValues without active session | Track session state machine |
| Bay transitions | StatusNotification follows valid bay FSM | Validate against BayStatus enum transitions |
| Response timing | Station responds to commands within 30s | Timeout timer per pending command |
| Idempotency | Duplicate messageId handled (not processed twice) | Track seen messageIds |
| Message format | All required envelope fields present (action, messageId, messageType, source, protocolVersion, timestamp, payload) | Validate envelope structure |
| Timestamp format | ISO 8601 with milliseconds: `yyyy-MM-ddTHH:mm:ss.SSSZ` | Regex validation |

### 8.3 Scoring Algorithm

```
total_tested = count(messages where status != 'not_tested')
passed = count(messages where status == 'passed')
score = passed / total_tested * 100

Overall: "18/22 (81.8%)"
```

Categories scored separately:
- Core (Boot, Heartbeat, Status, DataTransfer)
- Sessions (Start, Stop, MeterValues)
- Reservations
- Device Management
- Security

---

## 9. Deployment

### 9.1 Docker Compose Services

| Service | Image | Purpose |
|---------|-------|---------|
| app | Custom (Laravel) | API + WebSocket + queue dispatch |
| nginx | nginx:alpine | Reverse proxy, TLS termination |
| postgres | postgres:16-alpine | Persistent data |
| redis | redis:7-alpine | Queue + cache |
| emqx | emqx/emqx:5.8 | MQTT broker |
| queue-worker | Same as app | `queue:work` for MQTT message processing |
| scheduler | Same as app | `schedule:run` for maintenance jobs |
| emqx-init | curl | One-shot: configure EMQX webhook + ACL |

### 9.2 Domain + TLS

- Domain: `csms-sandbox.ospp-standard.org`
- TLS: Let's Encrypt via Certbot (auto-renew)
- MQTT TLS: port 8883, same Let's Encrypt cert
- MQTT plain: port 1883 (dev only, disabled in production)

### 9.3 Scheduled Jobs

| Job | Schedule | Purpose |
|-----|----------|---------|
| `messages:cleanup` | Daily 3AM | Delete messages older than 30 days |
| `tenants:inactive` | Weekly | Notify tenants inactive > 60 days |
| `station:check-connection` | Every 1min | Update is_connected based on heartbeat |

---

## 10. Limits

| Limit | Value | Rationale |
|-------|-------|-----------|
| Stations per tenant | 1 | Sandbox is for protocol testing, not load testing |
| Message history retention | 30 days | Storage management |
| MQTT rate limit | 100 msg/min | Prevent abuse |
| API rate limit | 60 req/min | Standard |
| Max payload size | 64 KB | OSPP spec limit |
| Max concurrent connections per tenant | 1 | One station = one connection |
| WebSocket connections per tenant | 3 | Dashboard tabs |

---

## 11. Security

| Concern | Mitigation |
|---------|-----------|
| Tenant isolation | EMQX ACL per username, PostgreSQL row-level filtering |
| MQTT credentials | bcrypt hashed, plain shown once (like API keys) |
| Authentication | JWT with ES256 (ECDSA P-256), 24h expiry |
| CSRF | SameSite cookies + CSRF token (Blade forms) |
| XSS | Blade auto-escaping, CSP headers |
| SQL injection | Eloquent ORM parameterized queries |
| Rate limiting | Per-tenant, per-endpoint |
| TLS | Enforced on MQTT (8883) and HTTPS |
| MQTT password storage | bcrypt in EMQX auth backend |

---

## 12. Out of Scope (v1)

- Payment / billing
- Team / organization accounts
- Custom protocol extensions
- Load testing (> 1 station per tenant)
- Mobile app
- MQTT over WebSocket (future consideration for browser-based station simulators)
- Webhook notifications to firmware developer's systems
- API keys (alternative to JWT for CI/CD integration — future)
- Multi-language dashboard (English only for v1)

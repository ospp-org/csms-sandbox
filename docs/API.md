# CSMS Sandbox — API Specification

---

## Base URL

```
https://csms-sandbox.ospp-standard.org/api/v1
```

## Authentication

JWT (ES256) via `Authorization: Bearer {token}` header.

Token obtained via `/auth/login` or `/auth/google`.
Token expiry: 24 hours.
Refresh: re-login (no refresh token for v1).

## Error Format

All errors return consistent JSON:

```json
{
    "error": "VALIDATION_ERROR",
    "message": "Human readable description",
    "details": {
        "field": ["Error for this field"]
    }
}
```

HTTP status codes:
- `200` — success
- `201` — created
- `400` — validation error
- `401` — unauthorized
- `403` — forbidden
- `404` — not found
- `422` — unprocessable entity
- `429` — rate limited
- `500` — server error

## Rate Limits

- Authentication: 30 requests/minute per IP
- API: 60 requests/minute per tenant
- MQTT: 100 messages/minute per tenant

Rate limit headers on every response:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 57
X-RateLimit-Reset: 1709986200
```

---

## 1. Authentication

### POST /auth/register

Create a new tenant account.

**Request:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "min8characters",
    "password_confirmation": "min8characters"
}
```

**Validation:**
- `name`: required, string, max 255
- `email`: required, email, unique
- `password`: required, min 8, confirmed

**Response (201):**
```json
{
    "token": "eyJ...",
    "tenant": {
        "id": "uuid",
        "name": "John Doe",
        "email": "john@example.com",
        "protocol_version": "0.1.0",
        "validation_mode": "strict"
    },
    "station": {
        "station_id": "stn_00000001",
        "mqtt_host": "csms-sandbox.ospp-standard.org",
        "mqtt_port": 8883,
        "mqtt_username": "sandbox_a1b2c3d4e5f6g7h8",
        "mqtt_password": "plain-text-shown-once"
    }
}
```

On register, station is auto-provisioned. MQTT password is shown once in this response. Tenant must save it.

---

### POST /auth/login

**Request:**
```json
{
    "email": "john@example.com",
    "password": "min8characters"
}
```

**Response (200):**
```json
{
    "token": "eyJ...",
    "tenant": {
        "id": "uuid",
        "name": "John Doe",
        "email": "john@example.com",
        "protocol_version": "0.1.0",
        "validation_mode": "strict"
    }
}
```

**Error (401):**
```json
{
    "error": "INVALID_CREDENTIALS",
    "message": "Email or password is incorrect"
}
```

---

### POST /auth/google

Google OAuth login/register.

**Request:**
```json
{
    "id_token": "google-oauth-id-token"
}
```

**Response (200):** Same as login. If tenant doesn't exist, auto-creates (same as register but without password).

---

### POST /auth/logout

Requires auth.

**Response (200):**
```json
{
    "message": "Logged out"
}
```

---

### GET /auth/me

Requires auth.

**Response (200):**
```json
{
    "id": "uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "protocol_version": "0.1.0",
    "validation_mode": "strict",
    "created_at": "2026-03-01T10:00:00.000Z"
}
```

---

## 2. Station

### GET /station

Get station info and MQTT credentials.

**Response (200):**
```json
{
    "station_id": "stn_00000001",
    "mqtt": {
        "host": "csms-sandbox.ospp-standard.org",
        "port_tls": 8883,
        "port_plain": 1883,
        "username": "sandbox_a1b2c3d4e5f6g7h8",
        "password_available": true
    },
    "topics": {
        "publish": "ospp/v1/stations/stn_00000001/to-server",
        "subscribe": "ospp/v1/stations/stn_00000001/to-station"
    },
    "status": {
        "connected": true,
        "last_connected_at": "2026-03-09T10:00:00.000Z",
        "last_boot_at": "2026-03-09T10:00:05.000Z",
        "firmware_version": "1.0.0",
        "station_model": "WashPro 5000",
        "station_vendor": "CSMS Dev",
        "bay_count": 4
    },
    "protocol_version": "0.1.0"
}
```

Note: `mqtt.password_available` indicates if password can be retrieved. Always true (encrypted at rest). Use regenerate to get new one.

---

### POST /station/regenerate-password

Generate new MQTT password. Old password stops working immediately.

**Response (200):**
```json
{
    "mqtt_password": "new-plain-text-password",
    "message": "Password regenerated. Old password is now invalid. Station must reconnect."
}
```

---

### GET /station/status

Real-time connection status.

**Response (200):**
```json
{
    "connected": true,
    "lifecycle": "online",
    "last_heartbeat": "2026-03-09T10:05:30.000Z",
    "bays": [
        {
            "bay_number": 1,
            "status": "available",
            "session_id": null,
            "reservation_id": null
        },
        {
            "bay_number": 2,
            "status": "occupied",
            "session_id": "sess_abc123",
            "reservation_id": null
        }
    ]
}
```

---

## 3. Messages

### GET /messages

Message history, paginated.

**Query params:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 50 | Items per page (max 100) |
| `action` | string | — | Filter by action (e.g., `BootNotification`) |
| `direction` | string | — | `inbound` or `outbound` |
| `schema_valid` | bool | — | `true` or `false` |
| `from` | datetime | — | ISO 8601 start time |
| `to` | datetime | — | ISO 8601 end time |
| `search` | string | — | Search in messageId or payload |

**Response (200):**
```json
{
    "data": [
        {
            "id": 12345,
            "direction": "inbound",
            "action": "BootNotification",
            "message_id": "msg_abc123",
            "message_type": "Request",
            "payload": { ... },
            "schema_valid": true,
            "validation_errors": null,
            "processing_time_ms": 45,
            "created_at": "2026-03-09T10:00:05.123Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 50,
        "total": 234,
        "last_page": 5
    }
}
```

---

### GET /messages/{id}

Single message with full detail.

**Response (200):**
```json
{
    "id": 12345,
    "direction": "inbound",
    "action": "BootNotification",
    "message_id": "msg_abc123",
    "message_type": "Request",
    "payload": {
        "action": "BootNotification",
        "messageId": "msg_abc123",
        "messageType": "Request",
        "source": "Station",
        "protocolVersion": "0.1.0",
        "timestamp": "2026-03-09T10:00:05.000Z",
        "payload": {
            "stationModel": "WashPro 5000",
            "stationVendor": "CSMS Dev",
            "firmwareVersion": "1.0.0",
            "bayCount": 4
        }
    },
    "schema_valid": true,
    "validation_errors": null,
    "processing_time_ms": 45,
    "created_at": "2026-03-09T10:00:05.123Z"
}
```

---

## 4. Commands

### POST /commands/{action}

Send a command to the connected station.

**URL param:** `action` — one of: `StartService`, `StopService`, `ReserveBay`, `CancelReservation`, `ChangeConfiguration`, `GetConfiguration`, `Reset`, `UpdateFirmware`, `UploadDiagnostics`, `SetMaintenanceMode`, `TriggerMessage`, `UpdateServiceCatalog`, `CertificateInstall`, `TriggerCertificateRenewal`

**Request body varies by action. Examples:**

#### StartService
```json
{
    "bayId": "bay_00000001",
    "serviceId": "svc_wash_basic",
    "sessionId": "sess_auto_generated"
}
```
If `sessionId` omitted, auto-generated.

#### Reset
```json
{
    "resetType": "Soft"
}
```

#### ChangeConfiguration
```json
{
    "keys": {
        "heartbeatInterval": "60",
        "meterValueSampleInterval": "10"
    }
}
```

#### GetConfiguration
```json
{
    "requestedKeys": ["heartbeatInterval", "meterValueSampleInterval"]
}
```
If `requestedKeys` omitted, requests all keys.

#### UpdateFirmware
```json
{
    "firmwareUrl": "https://firmware.example.com/v2.0.0.bin",
    "version": "2.0.0"
}
```

**Response (200):**
```json
{
    "status": "sent",
    "command_id": "uuid",
    "message_id": "msg_cmd_abc123",
    "action": "StartService",
    "message": "Command sent to station. Waiting for response."
}
```

**Error — station not connected (400):**
```json
{
    "error": "STATION_NOT_CONNECTED",
    "message": "Station is not connected. Connect via MQTT first."
}
```

---

### GET /commands/history

Command history with responses.

**Query params:** `page`, `per_page`, `action`, `status` (`sent`, `responded`, `timeout`)

**Response (200):**
```json
{
    "data": [
        {
            "id": "uuid",
            "action": "StartService",
            "payload": { ... },
            "status": "responded",
            "response_payload": {
                "status": "Accepted"
            },
            "response_time_ms": 230,
            "created_at": "2026-03-09T10:30:00.000Z",
            "response_received_at": "2026-03-09T10:30:00.230Z"
        }
    ],
    "meta": { ... }
}
```

---

### GET /commands/{action}/schema

Get JSON Schema for command parameters. Used by dashboard to auto-generate forms.

**Response (200):**
```json
{
    "action": "StartService",
    "schema": {
        "type": "object",
        "required": ["bayId", "serviceId"],
        "properties": {
            "bayId": {
                "type": "string",
                "pattern": "^bay_[a-f0-9]{8,}$"
            },
            "serviceId": {
                "type": "string",
                "pattern": "^svc_[a-z0-9_]+$"
            },
            "sessionId": {
                "type": "string",
                "pattern": "^sess_[a-f0-9]{16,}$",
                "description": "Auto-generated if omitted"
            }
        }
    }
}
```

---

## 5. Conformance

### GET /conformance

Full conformance report.

**Response (200):**
```json
{
    "protocol_version": "0.1.0",
    "score": {
        "passed": 18,
        "failed": 4,
        "partial": 0,
        "not_tested": 4,
        "total_tested": 22,
        "percentage": 81.8
    },
    "categories": {
        "core": { "passed": 4, "total": 4, "percentage": 100 },
        "sessions": { "passed": 3, "total": 4, "percentage": 75 },
        "reservations": { "passed": 2, "total": 2, "percentage": 100 },
        "device_management": { "passed": 5, "total": 7, "percentage": 71.4 },
        "security": { "passed": 4, "total": 5, "percentage": 80 }
    },
    "results": [
        {
            "action": "BootNotification",
            "category": "core",
            "status": "passed",
            "last_tested_at": "2026-03-09T10:00:05.000Z",
            "schema_valid": true,
            "behavior_checks": [
                { "rule": "boot_first", "passed": true },
                { "rule": "required_fields", "passed": true }
            ]
        },
        {
            "action": "MeterValues",
            "category": "sessions",
            "status": "failed",
            "last_tested_at": "2026-03-09T10:31:00.000Z",
            "schema_valid": false,
            "error_details": [
                {
                    "path": "/payload/readings/0/value",
                    "message": "Must be number, got string",
                    "expected": "number",
                    "actual": "string"
                }
            ],
            "behavior_checks": [
                { "rule": "session_active", "passed": true },
                { "rule": "meter_monotonic", "passed": false, "detail": "Value decreased from 150 to 120" }
            ]
        }
    ]
}
```

---

### GET /conformance/{action}

Detail for a single action.

**Response (200):** Single item from `results` array above.

---

### POST /conformance/reset

Reset all conformance results to `not_tested`.

**Response (200):**
```json
{
    "message": "Conformance results reset",
    "actions_reset": 26
}
```

---

### GET /conformance/export/pdf

Download conformance report as PDF.

**Response:** `Content-Type: application/pdf`, file download.

---

### GET /conformance/export/json

Download conformance report as JSON (machine-readable, for CI/CD).

**Response:** Same as `GET /conformance` but with `Content-Disposition: attachment`.

---

## 6. Settings

### GET /settings

**Response (200):**
```json
{
    "protocol_version": "0.1.0",
    "validation_mode": "strict",
    "available_versions": ["0.1.0"]
}
```

---

### PATCH /settings

**Request:**
```json
{
    "validation_mode": "lenient"
}
```

Or:
```json
{
    "protocol_version": "0.1.0"
}
```

Changing protocol version resets conformance results (different spec = different validation).

**Response (200):**
```json
{
    "protocol_version": "0.1.0",
    "validation_mode": "lenient",
    "message": "Settings updated"
}
```

---

## 7. Internal Endpoints

Not part of public API. Restricted to Docker network via Nginx.

### POST /internal/mqtt/webhook

EMQX webhook — receives MQTT messages.

**Request:**
```json
{
    "topic": "ospp/v1/stations/stn_00000001/to-server",
    "payload": "{...json string...}",
    "qos": 1
}
```

**Response (200):**
```json
{
    "status": "ok"
}
```

---

### POST /internal/mqtt/auth

EMQX authentication backend.

**Request:**
```json
{
    "username": "sandbox_a1b2c3d4",
    "password": "mqtt-password",
    "clientid": "station-client-id"
}
```

**Response (200):**
```json
{
    "result": "allow",
    "is_superuser": false
}
```

Or deny:
```json
{
    "result": "deny"
}
```

---

### POST /internal/mqtt/acl

EMQX authorization (topic ACL).

**Request:**
```json
{
    "username": "sandbox_a1b2c3d4",
    "topic": "ospp/v1/stations/stn_00000001/to-server",
    "action": "publish"
}
```

**Response (200):**
```json
{
    "result": "allow"
}
```

---

## 8. WebSocket (Laravel Reverb)

### Channel: `station.{stationId}`

Private channel. Tenant must be authenticated.

**Events broadcast:**

#### MessageReceived
```json
{
    "event": "MessageReceived",
    "data": {
        "id": 12345,
        "direction": "inbound",
        "action": "BootNotification",
        "message_id": "msg_abc123",
        "payload": { ... },
        "schema_valid": true,
        "validation_errors": null,
        "created_at": "2026-03-09T10:00:05.123Z"
    }
}
```

#### MessageSent
```json
{
    "event": "MessageSent",
    "data": {
        "id": 12346,
        "direction": "outbound",
        "action": "BootNotification",
        "message_id": "msg_abc123",
        "payload": { ... },
        "created_at": "2026-03-09T10:00:05.234Z"
    }
}
```

#### StationConnected / StationDisconnected
```json
{
    "event": "StationConnected",
    "data": {
        "station_id": "stn_00000001",
        "connected_at": "2026-03-09T10:00:00.000Z"
    }
}
```

### Authentication

Laravel Echo with Sanctum token:

```javascript
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverbAppKey,
    wsHost: window.location.hostname,
    wsPort: 8080,
    forceTLS: false,
    authEndpoint: '/api/v1/broadcasting/auth',
    auth: {
        headers: {
            Authorization: 'Bearer ' + token
        }
    }
});

Echo.private('station.' + stationId)
    .listen('MessageReceived', (e) => { ... })
    .listen('MessageSent', (e) => { ... })
    .listen('StationConnected', (e) => { ... })
    .listen('StationDisconnected', (e) => { ... });
```

# API Reference

Base URL: `https://csms-sandbox.ospp-standard.org/api/v1`

## Authentication

JWT Bearer token (ES256). Include in header: `Authorization: Bearer <token>`

Tokens expire after 1 hour. Re-authenticate via login.

---

## Auth Endpoints

### POST /auth/register

Create a new tenant account with auto-provisioned MQTT station.

**Auth:** None
**Rate limit:** 5 requests/minute per IP

**Request:**
```json
{
  "name": "My Company",
  "email": "dev@example.com",
  "password": "securepass123",
  "password_confirmation": "securepass123"
}
```

**Response (201):**
```json
{
  "token": "eyJ...",
  "tenant": {
    "id": 1,
    "name": "My Company",
    "email": "dev@example.com",
    "protocol_version": "0.1.0",
    "validation_mode": "strict"
  },
  "station": {
    "station_id": "stn_a1b2c3d4e5f6",
    "mqtt_host": "csms-sandbox.ospp-standard.org",
    "mqtt_port": 8883,
    "mqtt_username": "stn_a1b2c3d4e5f6",
    "mqtt_password": "generated-password"
  }
}
```

**Errors:**
- `422`: Validation error (email taken, password too short)
- `429`: Rate limited

### POST /auth/login

**Auth:** None
**Rate limit:** 5 requests/minute per IP

**Request:**
```json
{
  "email": "dev@example.com",
  "password": "securepass123"
}
```

**Response (200):**
```json
{
  "token": "eyJ...",
  "tenant": {
    "id": 1,
    "name": "My Company",
    "email": "dev@example.com",
    "protocol_version": "0.1.0",
    "validation_mode": "strict"
  }
}
```

**Errors:**
- `401`: `{"error": "INVALID_CREDENTIALS", "message": "Email or password is incorrect"}`
- `429`: Rate limited

### POST /auth/logout

**Auth:** JWT Bearer

**Response (200):**
```json
{"message": "Logged out"}
```

---

## Station Endpoints

### GET /station

Get station configuration and MQTT connection details.

**Auth:** JWT Bearer

**Response (200):**
```json
{
  "station_id": "stn_a1b2c3d4e5f6",
  "mqtt": {
    "host": "csms-sandbox.ospp-standard.org",
    "port_tls": 8883,
    "port_plain": 1883,
    "username": "stn_a1b2c3d4e5f6",
    "password_available": true
  },
  "topics": {
    "publish": "ospp/v1/stations/stn_a1b2c3d4e5f6/to-server",
    "subscribe": "ospp/v1/stations/stn_a1b2c3d4e5f6/to-station"
  },
  "status": {
    "connected": false,
    "last_connected_at": null,
    "firmware_version": null,
    "station_model": null,
    "station_vendor": null,
    "bay_count": 0
  },
  "protocol_version": "0.1.0"
}
```

### POST /station/regenerate-password

Rotate MQTT password. Station must reconnect with new password.

**Auth:** JWT Bearer

**Response (200):**
```json
{
  "mqtt_password": "new-generated-password",
  "message": "Password regenerated. Old password is now invalid. Station must reconnect."
}
```

### GET /station/status

Real-time station state from Redis.

**Auth:** JWT Bearer

**Response (200):**
```json
{
  "connected": true,
  "lifecycle": "online",
  "last_heartbeat": "2026-03-16T10:01:00.000Z",
  "bays": [
    {"bay_number": 1, "status": "Available", "session_id": null, "reservation_id": null},
    {"bay_number": 2, "status": "Occupied", "session_id": "sess_001", "reservation_id": null}
  ]
}
```

---

## Command Endpoints

### POST /commands/{action}

Send an OSPP command to the connected station.

**Auth:** JWT Bearer

**Actions:** StartService, StopService, ReserveBay, CancelReservation, Reset, ChangeConfiguration, GetConfiguration, UpdateFirmware, GetDiagnostics, SetMaintenanceMode, TriggerMessage, UpdateServiceCatalog, CertificateInstall, TriggerCertificateRenewal

**Request:** Action-specific JSON body (see schema endpoint for fields).

**Example (Reset):**
```json
{"type": "Soft"}
```

**Response (202):**
```json
{
  "command_id": 42,
  "message_id": "cmd_a1b2c3d4",
  "status": "sent"
}
```

**Errors:**
- `400`: `{"error": "INVALID_ACTION"}`
- `404`: `{"error": "NO_STATION"}`
- `409`: `{"error": "STATION_NOT_CONNECTED"}`
- `422`: `{"error": "VALIDATION_ERROR", "validation_errors": [...]}`

### GET /commands/history

Last 50 commands for this tenant.

**Auth:** JWT Bearer

**Response (200):**
```json
{
  "commands": [
    {
      "id": 42,
      "action": "Reset",
      "message_id": "cmd_a1b2c3d4",
      "status": "responded",
      "payload": {"type": "Soft"},
      "response_payload": {"status": "Accepted"},
      "response_received_at": "2026-03-16T10:01:05.000Z",
      "created_at": "2026-03-16T10:01:00.000Z"
    }
  ]
}
```

### GET /commands/{action}/schema

Get JSON Schema for a command's request payload.

**Auth:** JWT Bearer

**Response (200):**
```json
{
  "action": "Reset",
  "schema": {
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "type": "object",
    "required": ["type"],
    "properties": {
      "type": {"type": "string", "enum": ["Soft", "Hard"]}
    }
  }
}
```

**Errors:**
- `404`: `{"error": "UNKNOWN_ACTION", "message": "No schema for action: ..."}`

---

## Conformance Endpoints

### GET /conformance

Full conformance report.

**Auth:** JWT Bearer

**Response (200):**
```json
{
  "protocol_version": "0.1.0",
  "score": {
    "passed": 15,
    "failed": 2,
    "partial": 1,
    "not_tested": 8,
    "total_tested": 18,
    "percentage": 83.3
  },
  "categories": {
    "core": {"passed": 4, "total": 4, "percentage": 100.0},
    "sessions": {"passed": 2, "total": 3, "percentage": 66.7}
  },
  "results": [
    {
      "action": "BootNotification",
      "status": "passed",
      "last_tested_at": "2026-03-16T10:00:00.000Z",
      "error_details": null,
      "behavior_checks": [
        {"rule": "boot_first", "passed": true, "detail": null},
        {"rule": "envelope_format", "passed": true, "detail": null}
      ]
    }
  ]
}
```

### GET /conformance/{action}

Single action conformance detail.

**Auth:** JWT Bearer

### POST /conformance/reset

Reset all conformance results to "not_tested".

**Auth:** JWT Bearer

**Response (200):**
```json
{"message": "Conformance results reset", "actions_reset": 26}
```

### GET /conformance/export/pdf

Download PDF conformance report.

**Auth:** JWT Bearer
**Content-Type:** application/pdf

### GET /conformance/export/json

Download JSON conformance report.

**Auth:** JWT Bearer
**Content-Type:** application/json

---

## Status Endpoint

### GET /status

Public health check (no auth required).

**Response (200):**
```json
{
  "status": "operational",
  "version": "0.2.0",
  "services": {
    "database": "ok",
    "redis": "ok",
    "emqx": "ok",
    "queue": "ok"
  }
}
```

**Response (503):** When any critical service reports "error", status becomes "degraded".

EMQX returns "unavailable" (not "error") when not configured — does NOT trigger 503.

---

## Internal Endpoints

Restricted to Docker network IPs (172.16.0.0/12, 10.0.0.0/8) by nginx.

### POST /internal/mqtt/auth

EMQX calls this to authenticate MQTT connections.

**Request:** `{"username": "stn_...", "password": "...", "clientid": "..."}`
**Response:** `{"result": "allow"}` or `{"result": "deny"}`

### POST /internal/mqtt/acl

EMQX calls this to authorize MQTT publish/subscribe per topic.

**Request:** `{"username": "stn_...", "topic": "ospp/v1/stations/stn_.../to-server", "action": "publish"}`
**Response:** `{"result": "allow"}` or `{"result": "deny"}`

Stations can only publish to their own `to-server` topic and subscribe to their own `to-station` topic.

### POST /internal/mqtt/webhook

EMQX webhook delivers MQTT messages for processing. Protected by `verify-emqx` middleware.

**Request:** `{"topic": "ospp/v1/stations/stn_.../to-server", "payload": "{...}"}`
**Response:** `{"status": "ok"}`

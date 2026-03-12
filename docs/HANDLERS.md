# CSMS Sandbox — Protocol Handlers

---

## Overview

21 inbound handlers (station → CSMS) and 14 outbound commands (CSMS → station, triggered from dashboard). Each handler receives a `HandlerContext`, processes per OSPP spec, returns `HandlerResult`.

All handlers share this flow (managed by MqttMessageDispatcher, not by handlers):

```
1. Message arrives (from queue job)
2. Validate schema (SchemaValidationService)
3. Log inbound message (MessageLogService)
4. Check validation mode (strict → reject if invalid, lenient → continue)
5. Route to handler
6. Handler processes → returns HandlerResult
7. Build response envelope
8. Publish response to EMQX
9. Log outbound message
10. Update conformance result
11. Broadcast to Reverb (dashboard)
```

Handlers only do step 6. Everything else is the dispatcher's job.

---

## Handler Interface

```php
interface OsppHandler
{
    public function handle(HandlerContext $context): HandlerResult;
}
```

```php
final readonly class HandlerContext
{
    public function __construct(
        public string $tenantId,
        public string $stationId,
        public string $action,
        public string $messageId,
        public string $messageType,
        public array $payload,       // inner payload (inside "payload" key)
        public array $envelope,      // full OSPP envelope
        public string $protocolVersion,
    ) {}
}

final readonly class HandlerResult
{
    private function __construct(
        public bool $success,
        public array $responsePayload,
        public ?string $errorCode = null,
        public ?string $errorText = null,
    ) {}

    public static function accepted(array $payload): self { ... }
    public static function rejected(string $code, string $text): self { ... }
    public static function acknowledged(): self { ... }  // For events (no response needed)
}
```

---

## Inbound Handlers (Station → CSMS)

### 1. BootNotificationHandler

**Action:** `BootNotification`
**Message Type:** `Request`
**Category:** Core

**Input payload:**
```json
{
    "stationModel": "WashPro 5000",
    "stationVendor": "CSMS Dev",
    "firmwareVersion": "1.0.0",
    "bayCount": 4
}
```

**Processing:**
1. Station is always "registered" in sandbox (auto-provisioned at tenant creation)
2. Update station info in Redis: model, vendor, firmware, bayCount
3. Initialize bay states in Redis (all bays → `unknown`)
4. Set lifecycle → `online`
5. Set heartbeat interval from config (default 30s)
6. Update DB: `last_boot_at`, `firmware_version`, `station_model`, `station_vendor`, `bay_count`

**Response payload:**
```json
{
    "status": "Accepted",
    "serverTime": "2026-03-09T10:00:05.000Z",
    "heartbeatIntervalSec": 30
}
```

**Conformance checks:**
- `boot_first`: Was this the first message after connect?
- `required_fields`: All required fields present?

---

### 2. HeartbeatHandler

**Action:** `Heartbeat`
**Message Type:** `Request`
**Category:** Core

**Input payload:** `{}` (empty)

**Processing:**
1. Refresh connection TTL in Redis
2. Log heartbeat timestamp

**Response payload:**
```json
{
    "serverTime": "2026-03-09T10:05:30.000Z"
}
```

**Conformance checks:**
- `heartbeat_timing`: Within ±10% of configured interval?

---

### 3. StatusNotificationHandler

**Action:** `StatusNotification`
**Message Type:** `Event`
**Category:** Core

**Input payload:**
```json
{
    "bayId": "bay_00000001",
    "bayStatus": "Available",
    "timestamp": "2026-03-09T10:00:06.000Z"
}
```

**Processing:**
1. Update bay status in Redis
2. Validate bay transition against FSM (see BayTransitionRule in CONFORMANCE.md)

**Response:** Acknowledged (empty response, confirms receipt).

**Conformance checks:**
- `bay_transition`: Valid transition? (e.g., `Unknown` → `Available` OK, `Available` → `Finishing` invalid)

---

### 4. MeterValuesHandler

**Action:** `MeterValues`
**Message Type:** `Event`
**Category:** Sessions

**Input payload:**
```json
{
    "bayId": "bay_00000001",
    "sessionId": "sess_abc123",
    "readings": [
        {
            "type": "liquidMl",
            "value": 1500,
            "timestamp": "2026-03-09T10:10:00.000Z"
        }
    ]
}
```

**Processing:**
1. Verify active session exists on bay (Redis)
2. Log meter readings

**Response:** Acknowledged.

**Conformance checks:**
- `session_active`: Is there an active session on this bay?
- `meter_monotonic`: Are readings monotonically increasing?

---

### 5. DataTransferHandler

**Action:** `DataTransfer`
**Message Type:** `Request`
**Category:** Core

**Input payload:**
```json
{
    "vendorId": "com.example",
    "dataId": "diagnostics",
    "data": "arbitrary string or JSON"
}
```

**Processing:** Log. Always accept.

**Response payload:**
```json
{
    "status": "Accepted",
    "data": null
}
```

---

### 6. SecurityEventHandler

**Action:** `SecurityEvent`
**Message Type:** `Event`
**Category:** Security

**Input payload:**
```json
{
    "eventType": "TamperDetected",
    "timestamp": "2026-03-09T10:00:00.000Z",
    "details": "Cabinet opened"
}
```

**Processing:** Log event. No state change.

**Response:** Acknowledged.

---

### 7. SignCertificateHandler

**Action:** `SignCertificate`
**Message Type:** `Request`
**Category:** Security

**Input payload:**
```json
{
    "csr": "-----BEGIN CERTIFICATE REQUEST-----\n...",
    "certificateType": "StationCertificate"
}
```

**Processing:**
1. Validate CSR format
2. Sign with sandbox CA (self-signed, for testing only)
3. Return signed certificate

**Response payload:**
```json
{
    "status": "Accepted",
    "certificate": "-----BEGIN CERTIFICATE-----\n..."
}
```

---

### 8-21. Response Handlers (station responses to CSMS commands)

These handle the station's response to commands sent from the dashboard.

**General pattern for all response handlers:**

```php
public function handle(HandlerContext $context): HandlerResult
{
    $status = $context->payload['status'] ?? 'Unknown';
    $commandHistory = $this->commandService->findPendingByMessageId($context->messageId);

    if ($commandHistory !== null) {
        $commandHistory->update([
            'status' => 'responded',
            'response_payload' => $context->payload,
            'response_received_at' => now(),
        ]);
    }

    // Action-specific state updates...

    return HandlerResult::acknowledged();
}
```

#### 8. StartServiceResponseHandler
- On `Accepted`: Set bay → `Occupied`, record session start
- On `Rejected`: Log rejection reason, mark session failed

#### 9. StopServiceResponseHandler
- On `Accepted`: Set bay → `Finishing`, schedule transition to `Available`
- On `Rejected`: Log, keep bay in current state

#### 10. ReserveBayResponseHandler
- On `Accepted`: Set bay → `Reserved`, record reservation
- On `Rejected`: Log, release reservation

#### 11. CancelReservationResponseHandler
- On `Accepted`: Set bay → `Available`, clear reservation
- On `Rejected`: Log

#### 12. ChangeConfigurationResponseHandler
- On `Accepted`: Update config in Redis
- On `Rejected`: Log, config unchanged

#### 13. GetConfigurationResponseHandler
- Process returned config values, store in Redis
- Log all key-value pairs

#### 14. ResetResponseHandler
- On `Accepted`: Expect station to disconnect and reboot
- Set lifecycle → `resetting`
- On `Rejected`: Log, no state change

#### 15. UpdateFirmwareResponseHandler
- Track firmware update status progression: `Downloading` → `Installing` → `Installed`
- On failure: log error

#### 16. UploadDiagnosticsResponseHandler
- Track diagnostics upload status
- Log upload URL used

#### 17. SetMaintenanceModeResponseHandler
- On `Accepted`: Update bay status(es) to `Unavailable` or restore
- On `Rejected`: Log

#### 18. TriggerMessageResponseHandler
- On `Accepted`: Expect the triggered message to arrive
- On `Rejected` / `NotImplemented`: Log

#### 19. UpdateServiceCatalogResponseHandler
- On `Accepted`: Services updated
- On `Rejected`: Log

#### 20. CertificateInstallResponseHandler
- On `Accepted`: Certificate installed
- On `Rejected`: Log, certificate not installed

#### 21. TriggerCertificateRenewalResponseHandler
- On `Accepted`: Expect SignCertificate to follow
- On `Rejected`: Log

---

## Outbound Commands (CSMS → Station)

Triggered from dashboard Command Center via `POST /api/v1/commands/{action}`.

Each command is built by `CommandService`:

```php
public function send(string $tenantId, string $action, array $parameters): CommandResult
{
    $station = $this->getStationForTenant($tenantId);

    if (!$station->is_connected) {
        return CommandResult::error('STATION_NOT_CONNECTED');
    }

    $messageId = 'msg_' . bin2hex(random_bytes(16));

    $envelope = [
        'action' => $action,
        'messageId' => $messageId,
        'messageType' => 'Request',
        'source' => 'CSMS',
        'protocolVersion' => $station->protocol_version,
        'timestamp' => now()->format('Y-m-d\TH:i:s.v\Z'),
        'payload' => $parameters,
    ];

    // Validate against schema
    $validation = $this->schemaValidator->validateOutbound($action, $parameters);
    if (!$validation->valid) {
        return CommandResult::validationError($validation->errors);
    }

    // Publish to EMQX
    $topic = "ospp/v1/stations/{$station->station_id}/to-station";
    $this->publisher->publish($topic, json_encode($envelope));

    // Log
    $this->messageLog->logOutbound($tenantId, $station->station_id, $action, $messageId, $envelope);

    // Track pending command
    $command = CommandHistory::create([...]);

    // Set 30s timeout
    $this->scheduleTimeout($command);

    return CommandResult::sent($command->id, $messageId);
}
```

### Command Parameters

#### StartService
```json
{
    "bayId": "bay_00000001",        // required
    "serviceId": "svc_wash_basic",   // required
    "sessionId": "sess_..."          // optional, auto-generated
}
```

#### StopService
```json
{
    "bayId": "bay_00000001",         // required
    "sessionId": "sess_abc123"       // required
}
```

#### ReserveBay
```json
{
    "bayId": "bay_00000001",         // required
    "reservationId": "rsv_...",      // optional, auto-generated
    "ttlMinutes": 15                 // optional, default 15
}
```

#### CancelReservation
```json
{
    "bayId": "bay_00000001",         // required
    "reservationId": "rsv_abc123"    // required
}
```

#### ChangeConfiguration
```json
{
    "keys": {                        // required
        "heartbeatInterval": "60",
        "meterValueSampleInterval": "10"
    }
}
```

#### GetConfiguration
```json
{
    "requestedKeys": ["key1", "key2"]  // optional, all if omitted
}
```

#### Reset
```json
{
    "resetType": "Soft"              // required: "Soft" or "Hard"
}
```

#### UpdateFirmware
```json
{
    "firmwareUrl": "https://...",    // required
    "version": "2.0.0"              // required
}
```

#### UploadDiagnostics
```json
{
    "uploadUrl": "https://..."       // required
}
```

#### SetMaintenanceMode
```json
{
    "enabled": true,                 // required
    "bayId": "bay_00000001"          // optional, all bays if omitted
}
```

#### TriggerMessage
```json
{
    "requestedMessage": "StatusNotification",  // required
    "bayId": "bay_00000001"                     // optional
}
```

#### UpdateServiceCatalog
```json
{
    "services": [                    // required
        {
            "serviceId": "svc_wash_basic",
            "serviceName": "Standard Wash",
            "pricingType": "per_minute",
            "priceCreditsPerMinute": 10
        }
    ]
}
```

#### CertificateInstall
```json
{
    "certificateType": "StationCertificate",   // required
    "certificate": "-----BEGIN CERTIFICATE..." // required
}
```

#### TriggerCertificateRenewal
```json
{
    "certificateType": "StationCertificate"    // required
}
```

---

## Station State Service

All handlers interact with station state via `StationStateService`:

```php
final class StationStateService
{
    // Station lifecycle
    public function getLifecycle(string $stationId): string;
    public function setLifecycle(string $stationId, string $lifecycle): void;

    // Bay management
    public function getBayStatus(string $stationId, int $bayNumber): string;
    public function setBayStatus(string $stationId, int $bayNumber, string $status): void;
    public function getBaySession(string $stationId, int $bayNumber): ?string;
    public function setBaySession(string $stationId, int $bayNumber, ?string $sessionId, ?string $serviceId): void;
    public function getBayReservation(string $stationId, int $bayNumber): ?string;
    public function setBayReservation(string $stationId, int $bayNumber, ?string $reservationId): void;
    public function getAllBays(string $stationId): array;

    // Config
    public function getConfig(string $stationId): array;
    public function setConfig(string $stationId, array $keys): void;

    // Connection
    public function refreshConnection(string $stationId): void;
    public function isConnected(string $stationId): bool;

    // Full reset (on boot)
    public function resetState(string $stationId, int $bayCount): void;
}
```

All backed by Redis hashes (see ARCHITECTURE.md for key patterns).

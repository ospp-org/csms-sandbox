# Conformance Engine

The CSMS Sandbox evaluates station protocol conformance against the [OSPP Specification](https://github.com/ospp-org/spec) using 14 behavior rules and JSON Schema validation.

## How It Works

1. Station sends MQTT message → EMQX webhook → Laravel queue
2. **Schema validation**: Payload validated against SDK JSON Schema (ospp/protocol)
3. **Behavior rules**: 14 rules check protocol-level compliance
4. **Result recorded**: Per-station, per-action status saved to database
5. **Report generated**: Aggregated scores by category per station

## Scoring

| Status | Meaning |
|--------|---------|
| **passed** | Schema valid + all behavior rules passed |
| **failed** | Schema validation failed |
| **partial** | Schema valid but one or more behavior rules failed |
| **not_tested** | Action not yet received from station |

**Score calculation:** `passed / (passed + failed + partial) × 100%`

## Per-Station Tracking

Conformance is tracked **per station**, not per tenant. Each station has its own conformance report with independent pass/fail status. Use `?station=stn_00000001` query parameter on API endpoints, or the station selector dropdown in the dashboard.

## 27 Tracked Actions

| Category | Actions |
|----------|---------|
| Core | BootNotification, Heartbeat, StatusNotification, DataTransfer |
| Sessions | MeterValues, StartService, StopService, SessionEnded |
| Reservations | ReserveBay, CancelReservation |
| Device Management | ChangeConfiguration, GetConfiguration, Reset, UpdateFirmware, GetDiagnostics, SetMaintenanceMode, TriggerMessage, UpdateServiceCatalog |
| Offline | AuthorizeOfflinePass, TransactionEvent |
| Notifications | ConnectionLost, DiagnosticsNotification, FirmwareStatusNotification |
| Security | SecurityEvent, SignCertificate, CertificateInstall, TriggerCertificateRenewal |

## 14 Behavior Rules

### 1. BootFirst
No action is processed before a successful BootNotification.

### 2. HeartbeatTiming
Heartbeats arrive within the configured interval ± 10% tolerance. Default interval: 30 seconds.

### 3. EnvelopeFormat
Every message has the 7 required envelope fields: action, messageId, messageType, source, protocolVersion, timestamp, payload. Timestamp matches ISO 8601 millisecond UTC format.

### 4. BayTransition
Bay status changes follow the valid state machine: Unknown→Available→Reserved→Occupied→Finishing→Available. Self-transitions (e.g., Available→Available) are permitted.

### 5. SessionState
MeterValues requires an active session on the bay. Checked via bayId→bayNumber mapping in Redis.

### 6. ResponseTiming
Station responses arrive within the OSPP spec timeout per action:

| Action | Timeout |
|--------|---------|
| ReserveBay, CancelReservation | 5s |
| StartService, StopService, TriggerMessage, TriggerCertificateRenewal | 10s |
| AuthorizeOfflinePass | 15s |
| BootNotification, Heartbeat, GetConfiguration, Reset, SetMaintenanceMode, UpdateServiceCatalog, SignCertificate, CertificateInstall, DataTransfer | 30s |
| ChangeConfiguration, TransactionEvent | 60s |
| UpdateFirmware, GetDiagnostics | 300s |

### 7. Idempotency
Each messageId must be unique per station. Duplicate messageIds are flagged.

### 8. FirmwareUpdateSequence
FirmwareStatusNotification must follow: Downloading → Downloaded → Installing → Installed. Any state can transition to Failed.

### 9. DiagnosticsUpload
DiagnosticsNotification must follow: Collecting → Uploading → Uploaded. Any state can transition to Failed.

### 10. OfflineTransaction
TransactionEvent txCounter must be strictly monotonic (no duplicates, no gaps). Violations indicate replay attacks or lost transactions.

### 11. MeterValuesFrequency
MeterValues must have non-negative values and timestamps that advance forward (no backwards timestamps).

### 12. ReservationExpiry
ReserveBay commands must include an expirationTime in the future.

### 13. CertificateFormat
SignCertificate CSR must be PEM-encoded (starts with `-----BEGIN CERTIFICATE REQUEST-----`) with a valid certificateType.

### 14. ConfigurationPersistence
ChangeConfiguration responses with Rejected/NotSupported status must include errorCode and errorText.

## Schema Validation

Payloads are validated against JSON Schema files from the ospp/protocol SDK:
- 46 MQTT schemas in `vendor/ospp/protocol/schemas/mqtt/`
- 17 common schemas in `vendor/ospp/protocol/schemas/common/`
- JSON Schema Draft 2020-12 with `$ref`, `if/then/else` conditionals
- Validator: opis/json-schema with registered `$ref` prefix resolution

## Protocol Conformance Chain

```
OSPP Specification (spec repo)
        ↓
ospp/protocol SDK (JSON Schemas + PHP Enums)
        ↓
CSMS Sandbox (validates inbound messages against SDK schemas)
        ↓
Conformance Report (per-action pass/fail with behavior checks)
```

## Export Formats

### PDF Report
- Station details, protocol version
- Per-action status with color coding
- Behavior check details
- Category scores

### JSON Report
- Machine-readable format
- Same data as PDF
- Suitable for CI/CD integration

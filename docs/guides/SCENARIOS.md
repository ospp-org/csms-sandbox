# OSPP Protocol Scenarios

Practical guides with exact MQTT messages for common workflows.

For visual sequence diagrams, see the OSPP spec examples:
https://github.com/ospp-org/spec/tree/main/examples/flows

**Convention:** Action names are CONSTANT. `messageType` differentiates Request/Response/Event.

## a) Complete Wash Session

### 1. Boot + Status

```json
// Station -> CSMS (Request)
{
  "action": "BootNotification",
  "messageId": "msg_001",
  "messageType": "Request",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T10:00:00.000Z",
  "payload": {
    "stationId": "stn_a1b2c3d4e5f6",
    "firmwareVersion": "1.0.0",
    "stationModel": "WashPro-2000",
    "stationVendor": "AcmeWash",
    "serialNumber": "WP2K-001234",
    "bayCount": 2,
    "uptimeSeconds": 0,
    "pendingOfflineTransactions": 0,
    "timezone": "Europe/Bucharest",
    "bootReason": "PowerOn",
    "capabilities": {
      "bleSupported": true,
      "offlineModeSupported": true,
      "meterValuesSupported": true
    },
    "networkInfo": {"connectionType": "Ethernet"}
  }
}

// CSMS -> Station (Response)
{
  "action": "BootNotification",
  "messageId": "msg_001",
  "messageType": "Response",
  "source": "Server",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T10:00:00.123Z",
  "payload": {
    "status": "Accepted",
    "serverTime": "2026-03-16T10:00:00.123Z",
    "heartbeatIntervalSec": 30
  }
}
```

### 2. Bay Status Reports (Events, no response)

```json
// Station -> CSMS (Event)
{
  "action": "StatusNotification",
  "messageId": "msg_002",
  "messageType": "Event",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T10:00:01.000Z",
  "payload": {
    "bayId": "bay_00000001",
    "bayNumber": 1,
    "status": "Available",
    "services": [
      {"serviceId": "svc_wash_basic", "available": true},
      {"serviceId": "svc_wash_premium", "available": true}
    ]
  }
}
```

### 3. CSMS Sends StartService Command

```json
// CSMS -> Station (Request)
{
  "action": "StartService",
  "messageId": "cmd_start_001",
  "messageType": "Request",
  "source": "Server",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T10:05:00.000Z",
  "payload": {
    "sessionId": "sess_a1b2c3d4e5f6",
    "bayId": "bay_00000001",
    "serviceId": "svc_wash_basic",
    "durationSeconds": 300,
    "sessionSource": "MobileApp"
  }
}

// Station -> CSMS (Response, same action name)
{
  "action": "StartService",
  "messageId": "cmd_start_001",
  "messageType": "Response",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T10:05:00.500Z",
  "payload": {
    "status": "Accepted"
  }
}
```

### 4. Bay Transitions to Occupied

```json
// Station -> CSMS (Event)
{
  "action": "StatusNotification",
  "messageId": "msg_010",
  "messageType": "Event",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T10:05:01.000Z",
  "payload": {
    "bayId": "bay_00000001",
    "bayNumber": 1,
    "status": "Occupied",
    "previousStatus": "Available",
    "services": [{"serviceId": "svc_wash_basic", "available": false}]
  }
}
```

### 5. MeterValues During Session (every 15s)

```json
// Station -> CSMS (Event)
{
  "action": "MeterValues",
  "messageId": "msg_011",
  "messageType": "Event",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T10:05:15.000Z",
  "payload": {
    "bayId": "bay_00000001",
    "sessionId": "sess_a1b2c3d4e5f6",
    "timestamp": "2026-03-16T10:05:15.000Z",
    "values": {
      "liquidMl": 500,
      "consumableMl": 50,
      "energyWh": 25
    }
  }
}
```

### 6. CSMS Sends StopService

```json
// CSMS -> Station (Request)
{
  "action": "StopService",
  "messageId": "cmd_stop_001",
  "messageType": "Request",
  "source": "Server",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T10:10:00.000Z",
  "payload": {
    "sessionId": "sess_a1b2c3d4e5f6",
    "bayId": "bay_00000001"
  }
}

// Station -> CSMS (Response)
{
  "action": "StopService",
  "messageId": "cmd_stop_001",
  "messageType": "Response",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T10:10:00.800Z",
  "payload": {
    "status": "Accepted",
    "actualDurationSeconds": 300,
    "creditsCharged": 100,
    "meterValues": {
      "liquidMl": 5000,
      "consumableMl": 500,
      "energyWh": 250
    }
  }
}
```

### 7. Bay Returns to Available

```json
// Occupied -> Finishing (Event)
// Then: Finishing -> Available (Event)
{
  "action": "StatusNotification",
  "messageId": "msg_020",
  "messageType": "Event",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T10:10:05.000Z",
  "payload": {
    "bayId": "bay_00000001",
    "bayNumber": 1,
    "status": "Available",
    "previousStatus": "Finishing",
    "services": [
      {"serviceId": "svc_wash_basic", "available": true},
      {"serviceId": "svc_wash_premium", "available": true}
    ]
  }
}
```

### 8. SessionEnded (Timer Expiry — no StopService)

When the session timer expires, the station auto-stops and sends SessionEnded EVENT (not StopService Response):

```json
// Station -> CSMS (Event — no response expected)
{
  "action": "SessionEnded",
  "messageId": "msg_se_001",
  "messageType": "Event",
  "source": "Station",
  "protocolVersion": "0.2.1",
  "timestamp": "2026-03-16T10:10:00.000Z",
  "payload": {
    "sessionId": "sess_a1b2c3d4e5f6",
    "bayId": "bay_00000001",
    "reason": "TimerExpired",
    "actualDurationSeconds": 300,
    "creditsCharged": 100,
    "meterValues": {
      "liquidMl": 5000,
      "consumableMl": 500,
      "energyWh": 250
    }
  }
}
```

`reason` values: `TimerExpired` (normal), `Fault` (hardware error). Always followed by StatusNotification (Finishing→Available or Faulted).

## b) Firmware Update Flow

```json
// 1. CSMS -> Station: UpdateFirmware Request
{
  "action": "UpdateFirmware",
  "messageId": "cmd_fw_001",
  "messageType": "Request",
  "source": "Server",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T12:00:00.000Z",
  "payload": {
    "firmwareUrl": "https://firmware.example.com/washpro-1.3.0.bin",
    "firmwareVersion": "1.3.0",
    "checksum": "sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
    "signature": "MEUCIH+7FAVx..."
  }
}

// 2. Station -> CSMS: UpdateFirmware Response (Accepted)
// 3. Station -> CSMS: FirmwareStatusNotification (Downloading, 0%)
// 4. Station -> CSMS: FirmwareStatusNotification (Downloading, 50%)
// 5. Station -> CSMS: FirmwareStatusNotification (Downloaded)
// 6. Station -> CSMS: FirmwareStatusNotification (Installing)
// 7. Station reboots, sends BootNotification with firmwareVersion: "1.3.0"
// 8. Station -> CSMS: FirmwareStatusNotification (Installed)
```

```json
// FirmwareStatusNotification Event (step 3)
{
  "action": "FirmwareStatusNotification",
  "messageId": "msg_fw_001",
  "messageType": "Event",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T12:00:05.000Z",
  "payload": {
    "status": "Downloading",
    "firmwareVersion": "1.3.0",
    "progress": 0
  }
}
```

## c) Reservation Flow

```json
// 1. CSMS -> Station: ReserveBay Request
{
  "action": "ReserveBay",
  "messageId": "cmd_res_001",
  "messageType": "Request",
  "source": "Server",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T14:00:00.000Z",
  "payload": {
    "bayId": "bay_00000001",
    "reservationId": "res_a1b2c3d4",
    "expirationTime": "2026-03-16T14:03:00.000Z",
    "sessionSource": "WebPayment"
  }
}

// 2. Station -> CSMS: ReserveBay Response
{
  "action": "ReserveBay",
  "messageId": "cmd_res_001",
  "messageType": "Response",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T14:00:00.200Z",
  "payload": {"status": "Accepted"}
}

// 3. CSMS -> Station: CancelReservation (if user cancels)
{
  "action": "CancelReservation",
  "messageId": "cmd_cancel_001",
  "messageType": "Request",
  "source": "Server",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T14:02:00.000Z",
  "payload": {
    "bayId": "bay_00000001",
    "reservationId": "res_a1b2c3d4"
  }
}
```

## d) Configuration Management

```json
// 1. CSMS -> Station: ChangeConfiguration Request
{
  "action": "ChangeConfiguration",
  "messageId": "cmd_cfg_001",
  "messageType": "Request",
  "source": "Server",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T15:00:00.000Z",
  "payload": {
    "keys": [
      {"key": "HeartbeatInterval", "value": "60"},
      {"key": "MeterValuesInterval", "value": "30"}
    ]
  }
}

// 2. Station -> CSMS: ChangeConfiguration Response (per-key results)
{
  "action": "ChangeConfiguration",
  "messageId": "cmd_cfg_001",
  "messageType": "Response",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T15:00:00.300Z",
  "payload": {
    "results": [
      {"key": "HeartbeatInterval", "status": "Accepted"},
      {"key": "MeterValuesInterval", "status": "RebootRequired"}
    ]
  }
}

// 3. CSMS -> Station: GetConfiguration Request
{
  "action": "GetConfiguration",
  "messageId": "cmd_gcfg_001",
  "messageType": "Request",
  "source": "Server",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T15:01:00.000Z",
  "payload": {}
}

// 4. Station -> CSMS: GetConfiguration Response
{
  "action": "GetConfiguration",
  "messageId": "cmd_gcfg_001",
  "messageType": "Response",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T15:01:00.200Z",
  "payload": {
    "configuration": [
      {"key": "HeartbeatInterval", "value": "60", "readonly": false},
      {"key": "MeterValuesInterval", "value": "30", "readonly": false},
      {"key": "StationId", "value": "stn_a1b2c3d4e5f6", "readonly": true}
    ]
  }
}
```

## e) Diagnostics Upload

```json
// 1. CSMS -> Station: GetDiagnostics Request
{
  "action": "GetDiagnostics",
  "messageId": "cmd_diag_001",
  "messageType": "Request",
  "source": "Server",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T16:00:00.000Z",
  "payload": {
    "uploadUrl": "https://diag.example.com/upload/stn_a1b2c3d4e5f6"
  }
}

// 2. Station -> CSMS: GetDiagnostics Response
// 3. Station -> CSMS: DiagnosticsNotification (Collecting)
// 4. Station -> CSMS: DiagnosticsNotification (Uploading, progress: 50)
// 5. Station -> CSMS: DiagnosticsNotification (Uploaded, fileName: "diag_20260316.tar.gz")
```

## f) Certificate Lifecycle

```json
// 1. Station -> CSMS: SignCertificate Request
{
  "action": "SignCertificate",
  "messageId": "msg_cert_001",
  "messageType": "Request",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T17:00:00.000Z",
  "payload": {
    "certificateType": "StationCertificate",
    "csr": "-----BEGIN CERTIFICATE REQUEST-----\nMIIBxTCCAWugAwIBAgI...\n-----END CERTIFICATE REQUEST-----"
  }
}

// 2. CSMS -> Station: SignCertificate Response (acknowledgement only)
// 3. CSMS -> Station: CertificateInstall Request (delivers signed cert)
{
  "action": "CertificateInstall",
  "messageId": "cmd_cert_001",
  "messageType": "Request",
  "source": "Server",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T17:00:05.000Z",
  "payload": {
    "certificate": "-----BEGIN CERTIFICATE-----\nMIIC...\n-----END CERTIFICATE-----",
    "certificateType": "StationCertificate"
  }
}

// 4. Station -> CSMS: CertificateInstall Response
{
  "action": "CertificateInstall",
  "messageId": "cmd_cert_001",
  "messageType": "Response",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T17:00:05.500Z",
  "payload": {
    "status": "Accepted",
    "certificateSerialNumber": "01:23:45:67:89:AB:CD:EF"
  }
}
```

## g) Offline Reconciliation

After a station operates offline and reconnects:

```json
// Station -> CSMS: TransactionEvent Request (for each offline transaction)
{
  "action": "TransactionEvent",
  "messageId": "msg_tx_001",
  "messageType": "Request",
  "source": "Station",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T18:00:00.000Z",
  "payload": {
    "offlineTxId": "otx_a1b2c3d4",
    "offlinePassId": "opass_e5f6a7b8",
    "userId": "sub_user001",
    "bayId": "bay_00000001",
    "serviceId": "svc_wash_basic",
    "startedAt": "2026-03-16T15:00:00.000Z",
    "endedAt": "2026-03-16T15:05:00.000Z",
    "durationSeconds": 300,
    "creditsCharged": 100,
    "receipt": {
      "data": "eyJ0eCI6InRlc3QifQ==",
      "signature": "MEUCIH+7FAVx...",
      "signatureAlgorithm": "ECDSA-P256-SHA256"
    },
    "txCounter": 1
  }
}

// CSMS -> Station: TransactionEvent Response
{
  "action": "TransactionEvent",
  "messageId": "msg_tx_001",
  "messageType": "Response",
  "source": "Server",
  "protocolVersion": "0.1.0",
  "timestamp": "2026-03-16T18:00:00.500Z",
  "payload": {"status": "Accepted"}
}
```

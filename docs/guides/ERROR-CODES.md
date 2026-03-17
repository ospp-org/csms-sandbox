# OSPP Error Codes

Complete error code reference from the OSPP specification.

## Error Code Ranges

| Range | Category | Description |
|-------|----------|-------------|
| 1000-1999 | Transport | Network, protocol, message format |
| 2000-2999 | Authentication | Identity, credentials, access control |
| 3000-3999 | Session & Bay | Bay state, sessions, reservations |
| 4000-4999 | Payment & Credit | Wallet, credits, certificates |
| 5000-5999 | Hardware & Software | Physical faults, firmware, config |
| 6000-6999 | Server | Processing, timeouts, infrastructure |
| 9000-9999 | Vendor-Specific | Reserved for vendor implementations |

## Transport Errors (1000-1014)

| Code | Name | Description |
|------|------|-------------|
| 1000 | TRANSPORT_GENERIC | Unspecified transport error |
| 1001 | MQTT_CONNECTION_LOST | MQTT broker connection dropped |
| 1002 | MQTT_PUBLISH_FAILED | Failed to publish MQTT message |
| 1003 | TLS_HANDSHAKE_FAILED | TLS/SSL negotiation failed |
| 1004 | CERTIFICATE_ERROR | TLS certificate invalid or expired |
| 1005 | INVALID_MESSAGE_FORMAT | JSON parse error or missing envelope fields |
| 1006 | UNKNOWN_ACTION | Action name not recognized |
| 1007 | PROTOCOL_VERSION_MISMATCH | Incompatible protocol version |
| 1008 | BLE_RADIO_ERROR | BLE communication failure |
| 1009 | DNS_RESOLUTION_FAILED | Cannot resolve hostname |
| 1010 | MESSAGE_TIMEOUT | No response within timeout |
| 1011 | URL_UNREACHABLE | Cannot reach specified URL |
| 1012 | MAC_VERIFICATION_FAILED | HMAC signature mismatch |
| 1013 | MAC_MISSING | Required MAC field absent |
| 1014 | MESSAGE_TOO_LARGE | Message exceeds size limit |

## Authentication Errors (2000-2013)

| Code | Name | Description |
|------|------|-------------|
| 2000 | AUTH_GENERIC | Unspecified authentication error |
| 2001 | STATION_NOT_REGISTERED | Station ID not found in CSMS |
| 2002 | OFFLINE_PASS_INVALID | Offline pass signature verification failed |
| 2003 | OFFLINE_PASS_EXPIRED | Offline pass expiresAt has passed |
| 2004 | OFFLINE_EPOCH_REVOKED | Pass revocationEpoch below station minimum |
| 2005 | OFFLINE_COUNTER_REPLAY | Duplicate or out-of-order counter (fraud signal) |
| 2006 | OFFLINE_STATION_MISMATCH | Pass not bound to this station |
| 2007 | COMMAND_NOT_SUPPORTED | Action not implemented by station |
| 2008 | ACTION_NOT_PERMITTED | Station lacks permission for action |
| 2009 | JWT_EXPIRED | API JWT token expired |
| 2010 | JWT_INVALID | API JWT token malformed or invalid |
| 2011 | SESSION_TOKEN_EXPIRED | Web session token expired |
| 2012 | SESSION_TOKEN_INVALID | Web session token invalid |
| 2013 | BLE_AUTH_FAILED | BLE authentication handshake failed |

## Session & Bay Errors (3000-3016)

| Code | Name | Description |
|------|------|-------------|
| 3000 | SESSION_GENERIC | Unspecified session error |
| 3001 | BAY_BUSY | Bay is currently occupied |
| 3002 | BAY_NOT_READY | Bay hardware not initialized |
| 3003 | SERVICE_UNAVAILABLE | Requested service not available |
| 3004 | INVALID_SERVICE | Service ID not recognized |
| 3005 | BAY_NOT_FOUND | Bay ID not found |
| 3006 | SESSION_NOT_FOUND | Session ID not found |
| 3007 | SESSION_MISMATCH | Session ID doesn't match bay |
| 3008 | DURATION_INVALID | Requested duration out of range |
| 3009 | HARDWARE_ACTIVATION_FAILED | Physical equipment failed to start |
| 3010 | MAX_DURATION_EXCEEDED | Duration exceeds maximum allowed |
| 3011 | BAY_MAINTENANCE | Bay is in maintenance mode |
| 3012 | RESERVATION_NOT_FOUND | Reservation ID not found |
| 3013 | RESERVATION_EXPIRED | Reservation expirationTime passed |
| 3014 | BAY_RESERVED | Bay reserved for another user |
| 3015 | PAYLOAD_INVALID | Request payload validation failed |
| 3016 | ACTIVE_SESSIONS_PRESENT | Cannot reset while sessions active |

## Payment & Credit Errors (4000-4014)

| Code | Name | Description |
|------|------|-------------|
| 4000 | PAYMENT_GENERIC | Unspecified payment error |
| 4001 | INSUFFICIENT_BALANCE | Wallet balance too low |
| 4002 | OFFLINE_LIMIT_EXCEEDED | Offline spending limit reached |
| 4003 | OFFLINE_RATE_LIMITED | Too many offline transactions |
| 4004 | OFFLINE_PER_TX_EXCEEDED | Single transaction limit exceeded |
| 4005 | PAYMENT_FAILED | Payment processing failed |
| 4006 | PAYMENT_TIMEOUT | Payment processor timeout |
| 4007 | REFUND_FAILED | Refund processing failed |
| 4008 | WEBHOOK_SIGNATURE_INVALID | Payment webhook HMAC mismatch |
| 4010 | CSR_INVALID | Certificate signing request invalid |
| 4011 | CERTIFICATE_CHAIN_INVALID | Certificate chain verification failed |
| 4012 | CERTIFICATE_TYPE_MISMATCH | Wrong certificate type |
| 4013 | RENEWAL_DENIED | Certificate renewal not permitted |
| 4014 | KEYPAIR_GENERATION_FAILED | ECDSA key generation failed |

## Hardware & Software Errors (5000-5112)

### Hardware (5000-5009)

| Code | Name | Description |
|------|------|-------------|
| 5000 | HARDWARE_GENERIC | Unspecified hardware fault |
| 5001 | PUMP_SYSTEM | Pump or motor failure |
| 5002 | FLUID_SYSTEM | Fluid delivery issue |
| 5003 | CONSUMABLE_SYSTEM | Consumable supply issue |
| 5004 | ELECTRICAL_SYSTEM | Electrical fault |
| 5005 | PAYMENT_HARDWARE | Payment terminal fault |
| 5006 | HEATING_SYSTEM | Heating element fault |
| 5007 | MECHANICAL_SYSTEM | Mechanical component fault |
| 5008 | SENSOR_FAILURE | Sensor reading error |
| 5009 | EMERGENCY_STOP | Emergency stop triggered |

### Firmware Updates (5014-5018)

| Code | Name | Description |
|------|------|-------------|
| 5014 | DOWNLOAD_FAILED | Firmware download failed |
| 5015 | CHECKSUM_MISMATCH | Firmware checksum verification failed |
| 5016 | VERSION_ALREADY_INSTALLED | Firmware version already running |
| 5017 | INSUFFICIENT_STORAGE | Not enough storage for firmware |
| 5018 | INSTALLATION_FAILED | Firmware installation failed |
| 5112 | FIRMWARE_SIGNATURE_INVALID | Firmware signature verification failed |

### Diagnostics & Catalog (5019-5025)

| Code | Name | Description |
|------|------|-------------|
| 5019 | UPLOAD_FAILED | Diagnostics upload failed |
| 5020 | INVALID_TIME_WINDOW | Time window parameter invalid |
| 5021 | NO_DIAGNOSTICS_AVAILABLE | No diagnostics data to collect |
| 5023 | INVALID_CATALOG | Service catalog format invalid |
| 5024 | UNSUPPORTED_SERVICE | Service type not supported |
| 5025 | CATALOG_TOO_LARGE | Catalog exceeds size limit |

### Software (5100-5111)

| Code | Name | Description |
|------|------|-------------|
| 5100 | SOFTWARE_GENERIC | Unspecified software error |
| 5101 | FIRMWARE_ERROR | Critical firmware error |
| 5102 | CONFIGURATION_ERROR | Configuration parsing error |
| 5103 | STORAGE_ERROR | Persistent storage failure |
| 5104 | WATCHDOG_RESET | Watchdog timer triggered reset |
| 5105 | MEMORY_ERROR | Memory allocation failure |
| 5106 | CLOCK_ERROR | System clock error |
| 5107 | OPERATION_IN_PROGRESS | Another operation is running |
| 5108 | CONFIGURATION_KEY_READONLY | Attempted to modify read-only key |
| 5109 | INVALID_CONFIGURATION_VALUE | Value out of valid range |
| 5110 | RESET_FAILED | Station reset failed |
| 5111 | BUFFER_FULL | Internal buffer overflow |

## Server Errors (6000-6007)

| Code | Name | Description |
|------|------|-------------|
| 6000 | SERVER_GENERIC | Unspecified server error |
| 6001 | SERVER_INTERNAL_ERROR | Internal server error |
| 6002 | ACK_TIMEOUT | Station did not respond in time |
| 6003 | STATION_OFFLINE | Station not connected via MQTT |
| 6004 | VALIDATION_ERROR | Request validation failed |
| 6005 | SESSION_ALREADY_ACTIVE | User already has active session |
| 6006 | RATE_LIMIT_EXCEEDED | Too many requests |
| 6007 | SERVICE_DEGRADED | Server operating in degraded mode |

## Sandbox HTTP API Errors

| Status | Error Code | Description |
|--------|-----------|-------------|
| 401 | INVALID_CREDENTIALS | Wrong email or password |
| 401 | Unauthorized | JWT token missing or expired |
| 404 | NO_STATION | No station configured for tenant |
| 404 | UNKNOWN_ACTION | Command action not recognized |
| 409 | STATION_NOT_CONNECTED | Station not online |
| 422 | VALIDATION_ERROR | Request body validation failed |
| 429 | — | Rate limited (5 req/min on auth) |
| 503 | — | Service degraded (database/redis down) |

## Error Codes by Action

### BootNotification
- `Accepted`: Station registered successfully
- `Rejected`: Station not recognized (error 2001)
- `Pending`: Server not ready, retry after `retryInterval`

### StartService / StopService
- `Accepted`: Service started/stopped
- `Rejected`: 3001 (BAY_BUSY), 3003 (SERVICE_UNAVAILABLE), 3009 (HARDWARE_ACTIVATION_FAILED)

### ReserveBay / CancelReservation
- `Accepted`: Reservation created/cancelled
- `Rejected`: 3001 (BAY_BUSY), 3011 (BAY_MAINTENANCE), 3013 (RESERVATION_EXPIRED)

### Reset
- `Accepted`: Reset initiated
- `Rejected`: 3016 (ACTIVE_SESSIONS_PRESENT)

### ChangeConfiguration
- `Accepted`: Key applied immediately
- `RebootRequired`: Key applied after reboot
- `Rejected`: 5108 (KEY_READONLY), 5109 (INVALID_VALUE)
- `NotSupported`: Unknown key

### UpdateFirmware
- `Accepted`: Download started
- `Rejected`: 5015 (CHECKSUM_MISMATCH), 5016 (VERSION_ALREADY_INSTALLED), 5017 (INSUFFICIENT_STORAGE)

### SignCertificate
- `Accepted`: CSR received for processing
- `Rejected`: 4010 (CSR_INVALID)

### DataTransfer
- `Accepted`: Data processed
- `Rejected`: Generic rejection
- `UnknownVendor`: Vendor ID not recognized
- `UnknownData`: Data ID not recognized

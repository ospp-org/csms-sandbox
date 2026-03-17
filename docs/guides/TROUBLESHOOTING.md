# Troubleshooting Guide

Common problems and solutions when testing with the CSMS Sandbox.

## Connection Issues

### MQTT Connection Refused

**Symptom:** `Connection refused` or timeout when connecting to MQTT broker.

**Cause:** Wrong host, port, or credentials.

**Fix:**
1. Verify host: `csms-sandbox.ospp-standard.org`
2. Use port `8883` (TLS) or `1883` (plain)
3. Verify MQTT username = your `station_id` (e.g., `stn_a1b2c3d4`)
4. Verify MQTT password from Setup page or API

```bash
# Test connection with mosquitto_pub
mosquitto_pub -h csms-sandbox.ospp-standard.org -p 8883 \
  --capath /etc/ssl/certs \
  -u "stn_a1b2c3d4" -P "your_password" \
  -t "ospp/v1/stations/stn_a1b2c3d4/to-server" \
  -m '{"action":"Heartbeat","messageId":"test","messageType":"Request","source":"Station","protocolVersion":"0.1.0","timestamp":"2026-01-01T00:00:00.000Z","payload":{}}'
```

### TLS Handshake Failed

**Symptom:** `SSL/TLS handshake error` on port 8883.

**Cause:** Missing CA certificates or connecting to wrong port.

**Fix:**
- Ensure your TLS client trusts Let's Encrypt CA
- Use port `8883` for TLS, not `1883`
- For development, try plain MQTT on port `1883` first

```python
# Python: explicit CA path
client.tls_set(ca_certs="/etc/ssl/certs/ca-certificates.crt")
```

### ACL Denied

**Symptom:** Messages sent but never received by CSMS. No errors shown.

**Cause:** Publishing to wrong topic or subscribing to another tenant's topic.

**Fix:**
- Publish ONLY to: `ospp/v1/stations/{YOUR_station_id}/to-server`
- Subscribe ONLY to: `ospp/v1/stations/{YOUR_station_id}/to-station`
- Station ID must match your MQTT username

## Schema Validation

### Schema Validation Failed

**Symptom:** Conformance shows "failed" with schema validation errors.

**Cause:** Missing required fields, wrong types, or invalid values.

**Fix:** Check the error details in conformance results. Common issues:

| Error | Fix |
|-------|-----|
| `Missing required field: stationId` | Add all required fields per schema |
| `String should match pattern` | Check field format (e.g., `stn_[a-f0-9]{8,}`) |
| `Type mismatch: expected integer` | Send numbers as numbers, not strings |
| `Not in enum` | Check allowed values (e.g., `bootReason`) |

```json
// WRONG: bayCount as string
{"bayCount": "2"}

// CORRECT: bayCount as integer
{"bayCount": 2}
```

### Empty Array vs Empty Object

**Symptom:** `unknownKeys: []` fails validation with "must match type: array".

**Cause:** PHP JSON type erasure (known limitation).

**Fix:** The sandbox handles this via raw JSON passthrough for inbound messages. If testing via API, ensure your JSON serializer preserves `[]` as array and `{}` as object.

## Protocol Issues

### Boot Required First

**Symptom:** All messages rejected or conformance shows "boot_first" failure.

**Cause:** BootNotification not sent or not accepted.

**Fix:** Always send BootNotification as the first message after MQTT connect. Wait for `status: "Accepted"` before sending other messages.

### Heartbeat Timeout

**Symptom:** Conformance shows "heartbeat_timing" failure.

**Cause:** Heartbeat interval doesn't match the `heartbeatIntervalSec` from boot response.

**Fix:** Use the exact interval from the BootNotification response (default 30s). Tolerance is +/- 10%.

### Bay Transition Invalid

**Symptom:** Conformance shows "bay_transition" failure.

**Cause:** StatusNotification reports an invalid bay state change.

**Fix:** Follow the state machine:
```
Unknown -> Available, Faulted, Unavailable
Available -> Reserved, Occupied, Faulted, Unavailable
Reserved -> Available, Occupied, Faulted, Unavailable
Occupied -> Finishing, Faulted, Unavailable
Finishing -> Available, Faulted, Unavailable
Faulted -> Available, Unavailable
Unavailable -> Available, Faulted
Same -> Same (always valid)
```

### Command Not Received

**Symptom:** CSMS sends command but station never receives it.

**Cause:** Not subscribed to the correct topic.

**Fix:** Subscribe to `ospp/v1/stations/{station_id}/to-station` BEFORE sending BootNotification.

### MeterValues "No Active Session"

**Symptom:** Conformance shows "session_state" failure for MeterValues.

**Cause:** MeterValues sent for a bay without an active session.

**Fix:** Only send MeterValues after StartService is accepted and before StopService completes.

## API Issues

### JWT Token Expired

**Symptom:** `401 Unauthorized` on API calls.

**Cause:** JWT tokens expire after 1 hour.

**Fix:** Re-authenticate via `POST /api/v1/auth/login`.

### Rate Limited

**Symptom:** `429 Too Many Requests` on login/register.

**Cause:** More than 5 auth requests per minute from same IP.

**Fix:** Wait 60 seconds, then retry. Rate limit applies to login and register only.

## Conformance Issues

### "Not Tested" Status

**Symptom:** Action shows "not_tested" despite sending messages.

**Cause:** The specific action hasn't been received yet, or action name doesn't match.

**Fix:**
- Ensure action names match exactly (case-sensitive): `BootNotification`, not `bootNotification`
- For Response handlers: send with `messageType: "Response"` and the BASE action name (e.g., `StartService`, NOT `StartServiceResponse`)

### Response Timing Failure

**Symptom:** Conformance shows "response_timing" failure.

**Cause:** Station response arrived after the spec timeout.

**Fix:** Check per-action timeouts:

| Timeout | Actions |
|---------|---------|
| 5s | ReserveBay, CancelReservation |
| 10s | StartService, StopService, TriggerMessage, TriggerCertificateRenewal |
| 15s | AuthorizeOfflinePass |
| 30s | BootNotification, Heartbeat, Reset, GetConfiguration, and others |
| 60s | ChangeConfiguration, TransactionEvent |
| 300s | UpdateFirmware, GetDiagnostics |

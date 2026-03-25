# Quick Start Guide

Step-by-step guide for firmware developers to test their OSPP station implementation against the CSMS Sandbox.

## 1. Register

Visit [csms-sandbox.ospp-standard.org/register](https://csms-sandbox.ospp-standard.org/register) or use the API:

```bash
curl -X POST https://csms-sandbox.ospp-standard.org/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"My Company","email":"dev@example.com","password":"securepass","password_confirmation":"securepass"}'
```

Response includes JWT token and MQTT credentials.

## 2. Get MQTT Credentials

After login, visit the **Setup** page or call:

```bash
curl -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  https://csms-sandbox.ospp-standard.org/api/v1/station
```

You'll receive:
- `station_id`: Your unique station identifier (e.g., `stn_a1b2c3d4`)
- `mqtt_username`: Same as station_id
- `mqtt_password`: Generated password
- `mqtt_host`: csms-sandbox.ospp-standard.org
- `mqtt_port`: 8883 (TLS) or 1883 (plain)

## 3. Download Certificates (mTLS)

Port 8883 requires a client certificate (mTLS). Download from the **Setup** page or via API:

```bash
# Bulk download all station certificates
curl -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  https://csms-sandbox.ospp-standard.org/api/v1/simulator/certificates \
  -o certs.json

# Or download individually from dashboard:
# Setup page → Station Certificate → Download CA / Certificate / Private Key
```

You'll need 3 files per station:
- `ca.pem` — CA chain (same for all stations)
- `station.pem` — station client certificate
- `station-key.pem` — station private key

## 4. Connect via MQTT (with mTLS)

### Python (paho-mqtt)

```python
import paho.mqtt.client as mqtt
import json, time

STATION_ID = "stn_a1b2c3d4"
client = mqtt.Client()
client.username_pw_set(STATION_ID, "your_mqtt_password")
client.tls_set(
    ca_certs="ca.pem",
    certfile="station.pem",
    keyfile="station-key.pem",
)
client.connect("csms-sandbox.ospp-standard.org", 8883)

# Subscribe to commands from CSMS
client.subscribe(f"ospp/v1/stations/{STATION_ID}/to-station")
client.loop_start()
```

### JavaScript (MQTT.js)

```javascript
const mqtt = require('mqtt');
const fs = require('fs');
const STATION_ID = 'stn_a1b2c3d4';
const client = mqtt.connect('mqtts://csms-sandbox.ospp-standard.org:8883', {
  username: STATION_ID,
  password: 'your_mqtt_password',
  ca: fs.readFileSync('ca.pem'),
  cert: fs.readFileSync('station.pem'),
  key: fs.readFileSync('station-key.pem'),
});
client.subscribe(`ospp/v1/stations/${STATION_ID}/to-station`);
```

### C (ESP-IDF / Mosquitto)

```c
mosquitto_username_pw_set(mosq, "stn_a1b2c3d4", "your_mqtt_password");
mosquitto_tls_set(mosq, "ca.pem", NULL, "station.pem", "station-key.pem", NULL);
mosquitto_connect(mosq, "csms-sandbox.ospp-standard.org", 8883, 60);
```

## 5. Boot Notification (First Message)

Every station MUST send BootNotification as its first message:

```python
boot_msg = {
    "action": "BootNotification",
    "messageId": "msg_001",
    "messageType": "Request",
    "source": "Station",
    "protocolVersion": "0.1.0",
    "timestamp": "2026-03-16T10:00:00.000Z",
    "payload": {
        "stationId": STATION_ID,
        "firmwareVersion": "1.0.0",
        "stationModel": "MyStation",
        "stationVendor": "MyCompany",
        "serialNumber": "SN001",
        "bayCount": 2,
        "uptimeSeconds": 0,
        "pendingOfflineTransactions": 0,
        "timezone": "UTC",
        "bootReason": "PowerOn",
        "capabilities": {
            "bleSupported": False,
            "offlineModeSupported": False,
            "meterValuesSupported": True
        },
        "networkInfo": {"connectionType": "Ethernet"}
    }
}
client.publish(f"ospp/v1/stations/{STATION_ID}/to-server", json.dumps(boot_msg))
```

Expected response on `to-station` topic:

```json
{
  "action": "BootNotification",
  "messageType": "Response",
  "source": "Server",
  "payload": {
    "status": "Accepted",
    "serverTime": "2026-03-16T10:00:00.123Z",
    "heartbeatIntervalSec": 30
  }
}
```

## 6. Send Heartbeats

After boot, send heartbeats at the interval specified:

```python
heartbeat = {
    "action": "Heartbeat",
    "messageId": "msg_002",
    "messageType": "Request",
    "source": "Station",
    "protocolVersion": "0.1.0",
    "timestamp": "2026-03-16T10:00:30.000Z",
    "payload": {}
}
client.publish(f"ospp/v1/stations/{STATION_ID}/to-server", json.dumps(heartbeat))
```

## 7. Report Bay Status

Report each bay's status after boot:

```python
status = {
    "action": "StatusNotification",
    "messageId": "msg_003",
    "messageType": "Event",
    "source": "Station",
    "protocolVersion": "0.1.0",
    "timestamp": "2026-03-16T10:00:01.000Z",
    "payload": {
        "bayId": "bay_00000001",
        "bayNumber": 1,
        "status": "Available",
        "services": [{"serviceId": "svc_wash", "available": true}]
    }
}
```

Events don't receive a response.

## 8. Handle Commands

Subscribe to `to-station` topic. The CSMS sends commands like StartService:

```python
def on_message(client, userdata, msg):
    envelope = json.loads(msg.payload)
    action = envelope["action"]
    message_id = envelope["messageId"]

    if action == "StartService":
        response = {
            "action": "StartService",  # Same action name
            "messageId": message_id,   # Echo the messageId
            "messageType": "Response",
            "source": "Station",
            "protocolVersion": "0.1.0",
            "timestamp": now_iso(),
            "payload": {"status": "Accepted"}
        }
        client.publish(
            f"ospp/v1/stations/{STATION_ID}/to-server",
            json.dumps(response)
        )
```

## 9. Monitor in Dashboard

- **Live Monitor**: See every message in real-time (WebSocket)
- **Commands**: Send commands from the UI, see schemas
- **History**: Search and filter past messages
- **Conformance**: See your protocol compliance score

## 10. Check Conformance

Visit the **Conformance** page to see:
- Per-action pass/fail status
- 14 behavior rules with detailed error messages
- Schema validation results
- Category scores (Core, Sessions, Reservations, etc.)

Export as PDF or JSON for your records.

## 11. Common Issues

| Issue | Cause | Fix |
|-------|-------|-----|
| Boot rejected | Schema validation failed | Check all required fields |
| No response | Wrong topic | Publish to `to-server`, subscribe to `to-station` |
| 401 on API | Expired JWT | Re-login, token valid for 1 hour |
| ACL deny | Wrong credentials | Check mqtt_username matches station_id |
| Conformance "not tested" | Action not yet received | Send the action from your station |

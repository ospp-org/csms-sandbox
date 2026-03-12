import paho.mqtt.client as mqtt
import json, time

client = mqtt.Client()
client.username_pw_set("{{ $station->mqtt_username }}", "YOUR_PASSWORD")
client.tls_set()
client.connect("{{ config('sandbox.mqtt_public_host', 'csms-sandbox.ospp-standard.org') }}", {{ config('mqtt.tls_port', 8883) }})

station_id = "{{ $station->station_id }}"
client.subscribe(f"{{ config('mqtt.topic_prefix') }}/{station_id}/{{ config('mqtt.to_station_suffix') }}")

boot = {
    "action": "BootNotification",
    "messageId": "msg_001",
    "messageType": "Request",
    "source": "Station",
    "protocolVersion": "0.1.0",
    "timestamp": "2026-01-01T00:00:00.000Z",
    "payload": {
        "stationId": station_id,
        "stationModel": "MyStation",
        "stationVendor": "MyCompany",
        "firmwareVersion": "1.0.0",
        "serialNumber": "SN-001",
        "bayCount": 1,
        "bootReason": "PowerOn",
        "uptimeSeconds": 0,
        "pendingOfflineTransactions": 0,
        "timezone": "UTC",
        "capabilities": {
            "bleSupported": False,
            "offlineModeSupported": False,
            "meterValuesSupported": True
        },
        "networkInfo": {
            "connectionType": "Ethernet",
            "signalStrength": None
        }
    }
}

client.publish(
    f"{{ config('mqtt.topic_prefix') }}/{station_id}/{{ config('mqtt.to_server_suffix') }}",
    json.dumps(boot), qos=1)
client.loop_forever()

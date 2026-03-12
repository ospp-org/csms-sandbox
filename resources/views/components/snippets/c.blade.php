#include "mqtt_client.h"

esp_mqtt_client_config_t mqtt_cfg = {
    .broker.address.uri = "mqtts://{{ config('sandbox.mqtt_public_host', 'csms-sandbox.ospp-standard.org') }}",
    .broker.address.port = {{ config('mqtt.tls_port', 8883) }},
    .credentials.username = "{{ $station->mqtt_username }}",
    .credentials.authentication.password = "YOUR_PASSWORD",
};

esp_mqtt_client_handle_t client = esp_mqtt_client_init(&mqtt_cfg);
esp_mqtt_client_start(client);

// Subscribe to commands
esp_mqtt_client_subscribe(client,
    "{{ config('mqtt.topic_prefix') }}/{{ $station->station_id }}/{{ config('mqtt.to_station_suffix') }}", 1);

// Send BootNotification
const char *boot = "{\"action\":\"BootNotification\","
    "\"messageId\":\"msg_001\",\"messageType\":\"Request\","
    "\"source\":\"Station\",\"protocolVersion\":\"0.1.0\","
    "\"timestamp\":\"2026-01-01T00:00:00.000Z\","
    "\"payload\":{"
    "\"stationId\":\"{{ $station->station_id }}\","
    "\"stationModel\":\"MyStation\","
    "\"stationVendor\":\"MyCompany\","
    "\"firmwareVersion\":\"1.0.0\","
    "\"serialNumber\":\"SN-001\","
    "\"bayCount\":1,"
    "\"bootReason\":\"PowerOn\","
    "\"uptimeSeconds\":0,"
    "\"pendingOfflineTransactions\":0,"
    "\"timezone\":\"UTC\","
    "\"capabilities\":{\"bleSupported\":false,"
    "\"offlineModeSupported\":false,\"meterValuesSupported\":true},"
    "\"networkInfo\":{\"connectionType\":\"Ethernet\","
    "\"signalStrength\":null}}}";

esp_mqtt_client_publish(client,
    "{{ config('mqtt.topic_prefix') }}/{{ $station->station_id }}/{{ config('mqtt.to_server_suffix') }}",
    boot, 0, 1, 0);

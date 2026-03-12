const mqtt = require('mqtt');

const client = mqtt.connect('mqtts://{{ config('sandbox.mqtt_public_host', 'csms-sandbox.ospp-standard.org') }}:{{ config('mqtt.tls_port', 8883) }}', {
    username: '{{ $station->mqtt_username }}',
    password: 'YOUR_PASSWORD',
});

const stationId = '{{ $station->station_id }}';

client.on('connect', () => {
    client.subscribe(`{{ config('mqtt.topic_prefix') }}/${stationId}/{{ config('mqtt.to_station_suffix') }}`);

    client.publish(`{{ config('mqtt.topic_prefix') }}/${stationId}/{{ config('mqtt.to_server_suffix') }}`,
        JSON.stringify({
            action: 'BootNotification',
            messageId: 'msg_001',
            messageType: 'Request',
            source: 'Station',
            protocolVersion: '0.1.0',
            timestamp: new Date().toISOString().replace(/(\.\d{3})\d*Z/, '$1Z'),
            payload: {
                stationId: stationId,
                stationModel: 'MyStation',
                stationVendor: 'MyCompany',
                firmwareVersion: '1.0.0',
                serialNumber: 'SN-001',
                bayCount: 1,
                bootReason: 'PowerOn',
                uptimeSeconds: 0,
                pendingOfflineTransactions: 0,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                capabilities: {
                    bleSupported: false,
                    offlineModeSupported: false,
                    meterValuesSupported: true,
                },
                networkInfo: {
                    connectionType: 'Ethernet',
                    signalStrength: null,
                },
            }
        }),
        { qos: 1 }
    );
});

client.on('message', (topic, message) => {
    console.log('Received:', JSON.parse(message.toString()));
});

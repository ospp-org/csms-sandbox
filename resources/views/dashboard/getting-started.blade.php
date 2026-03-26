@extends('layouts.app')
@section('title', 'Getting Started')
@section('content')

<div class="max-w-3xl">
    <h2 class="text-2xl font-bold mb-2">Getting Started</h2>
    <p class="text-gray-600 mb-6">Connect your OSPP station to the sandbox, send messages, and get a conformance report.</p>

    {{-- Step 1: Connection Details --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold text-lg mb-4">Step 1 — Your Connection Details</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <label class="text-gray-500">Station ID</label>
                <p class="font-mono font-medium">{{ $station->station_id }}</p>
            </div>
            <div>
                <label class="text-gray-500">MQTT Host</label>
                <p class="font-mono">{{ config('sandbox.mqtt_public_host') }}</p>
            </div>
            <div>
                <label class="text-gray-500">MQTT Port</label>
                <p class="font-mono">8883 <span class="text-gray-400">(TLS + mTLS required)</span></p>
            </div>
            <div>
                <label class="text-gray-500">MQTT Username</label>
                <p class="font-mono">{{ $station->mqtt_username }}</p>
            </div>
            <div x-data="{ show: false, pwd: {!! json_encode($mqttPassword) !!} }">
                <label class="text-gray-500">MQTT Password</label>
                <div class="flex items-center space-x-2">
                    <p class="font-mono" x-text="show ? pwd : '********'"></p>
                    <button @click="show = !show" class="text-xs text-ospp-600 hover:underline" x-text="show ? 'Hide' : 'Show'"></button>
                </div>
            </div>
            <div>
                <label class="text-gray-500">Protocol Version</label>
                <p class="font-mono">{{ $tenant->protocol_version }}</p>
            </div>
        </div>
    </div>

    {{-- Step 2: Certificates --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold text-lg mb-3">Step 2 — Download Certificates</h3>
        <p class="text-sm text-gray-600 mb-4">Port 8883 requires mutual TLS (mTLS). Your station needs a client certificate signed by the sandbox CA.</p>

        @if($station->station_cert)
        <div class="flex flex-wrap gap-2 mb-3">
            <a href="/dashboard/certificates/ca" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-sm">CA Chain</a>
            <a href="/dashboard/certificates/cert" class="px-3 py-1.5 bg-ospp-500 hover:bg-ospp-600 text-white rounded text-sm">Certificate</a>
            <a href="/dashboard/certificates/key" class="px-3 py-1.5 bg-ospp-500 hover:bg-ospp-600 text-white rounded text-sm">Private Key</a>
        </div>
        <p class="text-xs text-gray-400">Or manage certificates from <a href="/dashboard/setup" class="text-ospp-600 underline">Setup</a>.</p>
        @else
        <div class="bg-yellow-50 border border-yellow-200 rounded p-3 text-sm text-yellow-800">
            Certificates not yet generated. <a href="/dashboard/setup" class="underline font-medium">Go to Setup</a> for details.
        </div>
        @endif
    </div>

    {{-- Step 3: Connect --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6" x-data="{ tab: 'python' }">
        <h3 class="font-semibold text-lg mb-3">Step 3 — Connect via MQTT</h3>
        <div class="flex space-x-2 mb-4">
            <button @click="tab = 'python'" :class="tab === 'python' ? 'bg-ospp-500 text-white' : 'bg-gray-100'" class="px-3 py-1 rounded text-sm">Python</button>
            <button @click="tab = 'js'" :class="tab === 'js' ? 'bg-ospp-500 text-white' : 'bg-gray-100'" class="px-3 py-1 rounded text-sm">JavaScript</button>
            <button @click="tab = 'c'" :class="tab === 'c' ? 'bg-ospp-500 text-white' : 'bg-gray-100'" class="px-3 py-1 rounded text-sm">C (ESP-IDF)</button>
        </div>
        <pre x-show="tab === 'python'" class="bg-gray-900 text-green-400 p-4 rounded text-xs overflow-x-auto"><code>import paho.mqtt.client as mqtt
import json

client = mqtt.Client()
client.username_pw_set("{{ $station->mqtt_username }}", "YOUR_PASSWORD")
client.tls_set(
    ca_certs="ca.pem",
    certfile="station.pem",
    keyfile="station-key.pem",
)
client.connect("{{ config('sandbox.mqtt_public_host') }}", 8883)
client.subscribe("{{ config('mqtt.topic_prefix') }}/{{ $station->station_id }}/{{ config('mqtt.to_station_suffix') }}")
client.loop_start()</code></pre>
        <pre x-show="tab === 'js'" class="bg-gray-900 text-green-400 p-4 rounded text-xs overflow-x-auto"><code>const mqtt = require('mqtt');
const fs = require('fs');
const client = mqtt.connect('mqtts://{{ config('sandbox.mqtt_public_host') }}:8883', {
  username: '{{ $station->mqtt_username }}',
  password: 'YOUR_PASSWORD',
  ca: fs.readFileSync('ca.pem'),
  cert: fs.readFileSync('station.pem'),
  key: fs.readFileSync('station-key.pem'),
});
client.subscribe('{{ config('mqtt.topic_prefix') }}/{{ $station->station_id }}/{{ config('mqtt.to_station_suffix') }}');</code></pre>
        <pre x-show="tab === 'c'" class="bg-gray-900 text-green-400 p-4 rounded text-xs overflow-x-auto"><code>mosquitto_username_pw_set(mosq, "{{ $station->mqtt_username }}", "YOUR_PASSWORD");
mosquitto_tls_set(mosq, "ca.pem", NULL, "station.pem", "station-key.pem", NULL);
mosquitto_connect(mosq, "{{ config('sandbox.mqtt_public_host') }}", 8883, 60);
mosquitto_subscribe(mosq, NULL,
  "{{ config('mqtt.topic_prefix') }}/{{ $station->station_id }}/{{ config('mqtt.to_station_suffix') }}", 1);</code></pre>
    </div>

    {{-- Step 4: BootNotification --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold text-lg mb-3">Step 4 — Send BootNotification</h3>
        <p class="text-sm text-gray-600 mb-3">Your first message must be a BootNotification. Publish to <code class="bg-gray-100 px-1 rounded text-xs">{{ config('mqtt.topic_prefix') }}/{{ $station->station_id }}/{{ config('mqtt.to_server_suffix') }}</code></p>
        <pre class="bg-gray-900 text-green-400 p-4 rounded text-xs overflow-x-auto"><code>{
  "action": "BootNotification",
  "messageId": "msg_001",
  "messageType": "Request",
  "source": "Station",
  "protocolVersion": "{{ $tenant->protocol_version }}",
  "timestamp": "2026-01-01T00:00:00.000Z",
  "payload": {
    "stationId": "{{ $station->station_id }}",
    "firmwareVersion": "1.0.0",
    "stationModel": "MyStation",
    "stationVendor": "MyCompany",
    "serialNumber": "SN-001",
    "bayCount": 2,
    "uptimeSeconds": 0,
    "pendingOfflineTransactions": 0,
    "timezone": "UTC",
    "bootReason": "PowerOn",
    "capabilities": {
      "bleSupported": false,
      "offlineModeSupported": false,
      "meterValuesSupported": true
    },
    "networkInfo": { "connectionType": "Ethernet" }
  }
}</code></pre>
        <p class="text-sm text-gray-500 mt-3">Expected response: <code class="bg-gray-100 px-1 rounded text-xs">{"status": "Accepted", "serverTime": "...", "heartbeatIntervalSec": 30}</code></p>
    </div>

    {{-- Step 5: Conformance --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold text-lg mb-3">Step 5 — Check Conformance</h3>
        <p class="text-sm text-gray-600 mb-3">After sending messages, check your conformance score. The sandbox validates every message against the OSPP spec — schema validation + 14 behavior rules.</p>
        <a href="/dashboard/conformance" class="inline-block px-4 py-2 bg-ospp-500 text-white rounded text-sm hover:bg-ospp-600">View Conformance Report</a>
    </div>

    {{-- Links --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-semibold text-lg mb-3">Resources</h3>
        <ul class="space-y-2 text-sm">
            <li><a href="https://github.com/ospp-org/spec" class="text-ospp-600 hover:underline" target="_blank">OSPP Protocol Specification</a></li>
            <li><a href="https://github.com/ospp-org/csms-sandbox/blob/main/docs/API.md" class="text-ospp-600 hover:underline" target="_blank">API Reference</a></li>
            <li><a href="https://github.com/ospp-org/csms-sandbox/blob/main/docs/QUICKSTART.md" class="text-ospp-600 hover:underline" target="_blank">Quick Start Guide</a></li>
            <li><a href="https://github.com/ospp-org/station-simulator" class="text-ospp-600 hover:underline" target="_blank">OSPP Station Simulator</a></li>
        </ul>
    </div>
</div>

@endsection

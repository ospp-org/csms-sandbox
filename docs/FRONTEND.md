# CSMS Sandbox — Frontend

---

## Stack

- **Blade templates** — server-rendered pages
- **Alpine.js 3.x** — via CDN, reactive UI
- **Tailwind CSS 3.x** — via CDN (Play CDN), utility classes
- **Laravel Echo** — WebSocket client for Reverb
- **Chart.js** — conformance score visualization

**No build process.** No npm, no vite, no webpack. Everything via CDN.

---

## Layout

### Base Layout (`resources/views/layouts/app.blade.php`)

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — OSPP CSMS Sandbox</title>

    <!-- Tailwind CSS (Play CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ospp: {
                            50: '#eff6ff',
                            500: '#2563eb',
                            600: '#1d4ed8',
                            700: '#1e40af',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @stack('head')
</head>
<body class="bg-gray-50 min-h-screen">

    @auth
        <div class="flex h-screen overflow-hidden">
            <!-- Sidebar -->
            @include('layouts.sidebar')

            <!-- Main content -->
            <main class="flex-1 overflow-y-auto p-6">
                @yield('content')
            </main>
        </div>
    @else
        @yield('content')
    @endauth

    @stack('scripts')
</body>
</html>
```

### Sidebar (`resources/views/layouts/sidebar.blade.php`)

```html
<aside class="w-64 bg-white border-r border-gray-200 flex flex-col">
    <!-- Logo -->
    <div class="p-4 border-b">
        <h1 class="text-lg font-bold text-ospp-700">OSPP Sandbox</h1>
        <p class="text-xs text-gray-500">Protocol Testing Environment</p>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-4 space-y-1">
        <a href="/dashboard/setup"
           class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('dashboard/setup') ? 'bg-ospp-50 text-ospp-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            Setup
        </a>
        <a href="/dashboard/monitor"
           class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('dashboard/monitor') ? 'bg-ospp-50 text-ospp-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            Live Monitor
        </a>
        <a href="/dashboard/commands"
           class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('dashboard/commands') ? 'bg-ospp-50 text-ospp-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            Command Center
        </a>
        <a href="/dashboard/conformance"
           class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('dashboard/conformance') ? 'bg-ospp-50 text-ospp-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            Conformance
        </a>
        <a href="/dashboard/history"
           class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('dashboard/history') ? 'bg-ospp-50 text-ospp-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            History
        </a>
        <a href="/dashboard/settings"
           class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('dashboard/settings') ? 'bg-ospp-50 text-ospp-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
            Settings
        </a>
    </nav>

    <!-- Connection status -->
    <div class="p-4 border-t" x-data="connectionStatus()">
        <div class="flex items-center space-x-2">
            <span class="w-2 h-2 rounded-full" :class="connected ? 'bg-green-500' : 'bg-red-500'"></span>
            <span class="text-sm text-gray-600" x-text="connected ? 'Station Connected' : 'Station Disconnected'"></span>
        </div>
        <p class="text-xs text-gray-400 mt-1">{{ $station->station_id }}</p>
    </div>

    <!-- User -->
    <div class="p-4 border-t">
        <p class="text-sm text-gray-600">{{ auth()->user()->name }}</p>
        <form method="POST" action="/auth/logout" class="mt-1">
            @csrf
            <button type="submit" class="text-xs text-red-600 hover:underline">Logout</button>
        </form>
    </div>
</aside>
```

---

## Pages

### Auth Pages

#### Login (`resources/views/auth/login.blade.php`)

Simple centered form:
- Email input
- Password input
- "Login" button
- "Sign in with Google" button
- "Register" link

#### Register (`resources/views/auth/register.blade.php`)

- Name, email, password, confirm password
- "Register" button
- "Sign in with Google" button
- "Already have an account? Login" link

After register: redirect to Setup page with MQTT credentials highlighted.

---

### Setup Page (`resources/views/dashboard/setup.blade.php`)

```html
@extends('layouts.app')
@section('title', 'Setup')
@section('content')

<div class="max-w-3xl">
    <h2 class="text-2xl font-bold mb-6">MQTT Connection</h2>

    <!-- Connection details card -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-500">Host</label>
                <p class="font-mono text-sm">csms-sandbox.ospp-standard.org</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Port (TLS)</label>
                <p class="font-mono text-sm">8883</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Username</label>
                <p class="font-mono text-sm">{{ $station->mqtt_username }}</p>
            </div>
            <div x-data="{ show: false }">
                <label class="text-sm text-gray-500">Password</label>
                <div class="flex items-center space-x-2">
                    <p class="font-mono text-sm" x-text="show ? '{{ $mqttPassword }}' : '••••••••'"></p>
                    <button @click="show = !show" class="text-xs text-ospp-600" x-text="show ? 'Hide' : 'Show'"></button>
                </div>
            </div>
            <div>
                <label class="text-sm text-gray-500">Station ID</label>
                <p class="font-mono text-sm">{{ $station->station_id }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Protocol Version</label>
                <p class="font-mono text-sm">{{ $station->protocol_version }}</p>
            </div>
        </div>

        <div class="mt-4 flex space-x-2">
            <button @click="regeneratePassword()" class="text-sm bg-red-50 text-red-600 px-3 py-1 rounded hover:bg-red-100">
                Regenerate Password
            </button>
        </div>
    </div>

    <!-- Topics -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold mb-3">MQTT Topics</h3>
        <div class="space-y-2 font-mono text-sm">
            <div>
                <span class="text-gray-500">Publish to:</span>
                <span>ospp/v1/stations/{{ $station->station_id }}/to-server</span>
            </div>
            <div>
                <span class="text-gray-500">Subscribe to:</span>
                <span>ospp/v1/stations/{{ $station->station_id }}/to-station</span>
            </div>
        </div>
    </div>

    <!-- Code snippets -->
    <div class="bg-white rounded-lg shadow p-6" x-data="{ tab: 'c' }">
        <h3 class="font-semibold mb-3">Quick Start Code</h3>
        <div class="flex space-x-2 mb-4">
            <button @click="tab = 'c'" :class="tab === 'c' ? 'bg-ospp-500 text-white' : 'bg-gray-100'" class="px-3 py-1 rounded text-sm">C (ESP-IDF)</button>
            <button @click="tab = 'python'" :class="tab === 'python' ? 'bg-ospp-500 text-white' : 'bg-gray-100'" class="px-3 py-1 rounded text-sm">Python</button>
            <button @click="tab = 'js'" :class="tab === 'js' ? 'bg-ospp-500 text-white' : 'bg-gray-100'" class="px-3 py-1 rounded text-sm">JavaScript</button>
        </div>
        <pre x-show="tab === 'c'" class="bg-gray-900 text-green-400 p-4 rounded text-xs overflow-x-auto"><code>@include('components.snippets.c')</code></pre>
        <pre x-show="tab === 'python'" class="bg-gray-900 text-green-400 p-4 rounded text-xs overflow-x-auto"><code>@include('components.snippets.python')</code></pre>
        <pre x-show="tab === 'js'" class="bg-gray-900 text-green-400 p-4 rounded text-xs overflow-x-auto"><code>@include('components.snippets.javascript')</code></pre>
    </div>
</div>

@endsection
```

---

### Live Monitor Page (`resources/views/dashboard/monitor.blade.php`)

```html
@extends('layouts.app')
@section('title', 'Live Monitor')
@section('content')

<div x-data="liveMonitor()" x-init="connect()">
    <!-- Header -->
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold">Live Messages</h2>
        <div class="flex space-x-2">
            <button @click="togglePause()" class="px-3 py-1 rounded text-sm"
                :class="paused ? 'bg-green-500 text-white' : 'bg-yellow-500 text-white'"
                x-text="paused ? 'Resume' : 'Pause'">
            </button>
            <button @click="clear()" class="px-3 py-1 rounded text-sm bg-gray-200 text-gray-700">Clear</button>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex space-x-4 mb-4">
        <select x-model="filterAction" class="text-sm border rounded px-2 py-1">
            <option value="">All actions</option>
            <template x-for="action in availableActions">
                <option :value="action" x-text="action"></option>
            </template>
        </select>
        <select x-model="filterDirection" class="text-sm border rounded px-2 py-1">
            <option value="">Both</option>
            <option value="inbound">⬆️ Inbound</option>
            <option value="outbound">⬇️ Outbound</option>
        </select>
        <select x-model="filterValid" class="text-sm border rounded px-2 py-1">
            <option value="">All</option>
            <option value="true">✅ Valid</option>
            <option value="false">❌ Invalid</option>
        </select>
    </div>

    <!-- Messages -->
    <div class="space-y-2 max-h-[calc(100vh-200px)] overflow-y-auto" id="message-list">
        <template x-for="msg in filteredMessages" :key="msg.id">
            <div class="bg-white rounded-lg shadow-sm border p-3 cursor-pointer"
                 @click="msg.expanded = !msg.expanded"
                 :class="msg.schema_valid === false ? 'border-red-200' : 'border-gray-100'">

                <!-- Message header -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <span class="text-xs text-gray-400 font-mono" x-text="formatTime(msg.created_at)"></span>
                        <span class="text-sm" x-text="msg.direction === 'inbound' ? '⬆️' : '⬇️'"></span>
                        <span class="text-sm font-medium" x-text="msg.action"></span>
                    </div>
                    <span x-text="msg.schema_valid === false ? '❌' : '✅'" class="text-sm"></span>
                </div>

                <!-- Expanded payload -->
                <div x-show="msg.expanded" x-transition class="mt-2">
                    <pre class="bg-gray-50 p-2 rounded text-xs overflow-x-auto"
                         x-text="JSON.stringify(msg.payload, null, 2)"></pre>

                    <!-- Validation errors -->
                    <template x-if="msg.validation_errors && msg.validation_errors.length > 0">
                        <div class="mt-2 bg-red-50 p-2 rounded">
                            <p class="text-xs font-medium text-red-700 mb-1">Validation Errors:</p>
                            <template x-for="err in msg.validation_errors">
                                <p class="text-xs text-red-600">
                                    <span class="font-mono" x-text="err.path"></span>:
                                    <span x-text="err.message"></span>
                                </p>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- Empty state -->
        <div x-show="messages.length === 0" class="text-center py-12 text-gray-400">
            <p class="text-lg">No messages yet</p>
            <p class="text-sm">Connect your station to see messages here</p>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.x.x/dist/echo.iife.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.x.x/dist/web/pusher.min.js"></script>
<script>
function liveMonitor() {
    return {
        messages: [],
        paused: false,
        filterAction: '',
        filterDirection: '',
        filterValid: '',
        availableActions: [
            'BootNotification', 'Heartbeat', 'StatusNotification',
            'MeterValues', 'DataTransfer', 'SecurityEvent',
            'SignCertificate', 'StartService', 'StopService',
            'ReserveBay', 'CancelReservation', 'Reset',
            'ChangeConfiguration', 'GetConfiguration',
            'UpdateFirmware', 'UploadDiagnostics',
            'SetMaintenanceMode', 'TriggerMessage',
            'UpdateServiceCatalog', 'CertificateInstall',
            'TriggerCertificateRenewal'
        ],

        get filteredMessages() {
            return this.messages.filter(msg => {
                if (this.filterAction && msg.action !== this.filterAction) return false;
                if (this.filterDirection && msg.direction !== this.filterDirection) return false;
                if (this.filterValid === 'true' && msg.schema_valid !== true) return false;
                if (this.filterValid === 'false' && msg.schema_valid !== false) return false;
                return true;
            });
        },

        connect() {
            const stationId = '{{ $station->station_id }}';
            window.Echo = new Echo({
                broadcaster: 'reverb',
                key: '{{ config("reverb.apps.0.key") }}',
                wsHost: window.location.hostname,
                wsPort: {{ config('reverb.apps.0.options.port', 8080) }},
                forceTLS: false,
            });

            Echo.private('station.' + stationId)
                .listen('MessageReceived', (e) => {
                    if (!this.paused) {
                        e.message.expanded = false;
                        this.messages.unshift(e.message);
                        if (this.messages.length > 1000) this.messages.pop();
                    }
                })
                .listen('MessageSent', (e) => {
                    if (!this.paused) {
                        e.message.expanded = false;
                        this.messages.unshift(e.message);
                        if (this.messages.length > 1000) this.messages.pop();
                    }
                });
        },

        togglePause() { this.paused = !this.paused; },
        clear() { this.messages = []; },
        formatTime(ts) {
            const d = new Date(ts);
            return d.toLocaleTimeString('en-US', { hour12: false, fractionalSecondDigits: 3 });
        }
    };
}
</script>
@endpush

@endsection
```

---

### Command Center Page (`resources/views/dashboard/commands.blade.php`)

Key Alpine component:

```javascript
function commandCenter() {
    return {
        selectedAction: '',
        schema: null,
        formData: {},
        sending: false,
        recentCommands: [],

        async selectAction(action) {
            this.selectedAction = action;
            const resp = await fetch(`/api/v1/commands/${action}/schema`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            this.schema = await resp.json();
            this.formData = this.buildDefaults(this.schema.schema);
        },

        buildDefaults(schema) {
            const data = {};
            for (const [key, prop] of Object.entries(schema.properties || {})) {
                if (prop.default !== undefined) data[key] = prop.default;
                else if (prop.type === 'string') data[key] = '';
                else if (prop.type === 'number' || prop.type === 'integer') data[key] = 0;
                else if (prop.type === 'boolean') data[key] = false;
            }
            return data;
        },

        async send() {
            this.sending = true;
            const resp = await fetch(`/api/v1/commands/${this.selectedAction}`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(this.formData)
            });
            const result = await resp.json();
            this.recentCommands.unshift({
                action: this.selectedAction,
                status: result.status,
                time: new Date().toLocaleTimeString(),
            });
            this.sending = false;
        }
    };
}
```

---

### Conformance Page (`resources/views/dashboard/conformance.blade.php`)

Server-rendered with Chart.js for score visualization:

```html
<!-- Score circle -->
<canvas id="scoreChart" width="200" height="200"></canvas>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.x.x/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('scoreChart'), {
    type: 'doughnut',
    data: {
        labels: ['Passed', 'Failed', 'Not Tested'],
        datasets: [{
            data: [{{ $report->passed }}, {{ $report->failed }}, {{ $report->notTested }}],
            backgroundColor: ['#22c55e', '#ef4444', '#e5e7eb'],
        }]
    },
    options: {
        cutout: '70%',
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>
```

Checklist rendered as server-side table with status badges.

---

### History Page (`resources/views/dashboard/history.blade.php`)

Server-rendered paginated table. Uses `{{ $messages->links() }}` for pagination. Filters via query params (server-side filtering).

---

### Settings Page (`resources/views/dashboard/settings.blade.php`)

Simple form:
- Protocol version dropdown
- Validation mode toggle (strict/lenient)
- Save button
- Warning: "Changing protocol version resets conformance results"

---

## Components

### Connection Status (`resources/views/components/connection-status.blade.php`)

```javascript
function connectionStatus() {
    return {
        connected: {{ $station->is_connected ? 'true' : 'false' }},
        init() {
            Echo.private('station.{{ $station->station_id }}')
                .listen('StationConnected', () => { this.connected = true; })
                .listen('StationDisconnected', () => { this.connected = false; });
        }
    };
}
```

### Conformance Badge (`resources/views/components/conformance-badge.blade.php`)

```html
@props(['status'])

@php
$classes = match($status) {
    'passed' => 'bg-green-100 text-green-800',
    'failed' => 'bg-red-100 text-red-800',
    'partial' => 'bg-yellow-100 text-yellow-800',
    'not_tested' => 'bg-gray-100 text-gray-500',
};
$icon = match($status) {
    'passed' => '✅',
    'failed' => '❌',
    'partial' => '⚠️',
    'not_tested' => '⚪',
};
@endphp

<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $classes }}">
    {{ $icon }} {{ ucfirst(str_replace('_', ' ', $status)) }}
</span>
```

---

## Code Snippets (Setup Page)

### C (ESP-IDF) — `resources/views/components/snippets/c.blade.php`

```c
#include "mqtt_client.h"

esp_mqtt_client_config_t mqtt_cfg = {
    .broker.address.uri = "mqtts://csms-sandbox.ospp-standard.org",
    .broker.address.port = 8883,
    .credentials.username = "{{ $station->mqtt_username }}",
    .credentials.authentication.password = "YOUR_PASSWORD",
};

esp_mqtt_client_handle_t client = esp_mqtt_client_init(&mqtt_cfg);
esp_mqtt_client_start(client);

// Subscribe to commands
esp_mqtt_client_subscribe(client,
    "ospp/v1/stations/{{ $station->station_id }}/to-station", 1);

// Send BootNotification
const char *boot = "{\"action\":\"BootNotification\","
    "\"messageId\":\"msg_001\",\"messageType\":\"Request\","
    "\"source\":\"Station\",\"protocolVersion\":\"0.1.0\","
    "\"timestamp\":\"2026-01-01T00:00:00.000Z\","
    "\"payload\":{\"stationModel\":\"MyStation\","
    "\"stationVendor\":\"MyCompany\",\"firmwareVersion\":\"1.0.0\","
    "\"bayCount\":1}}";

esp_mqtt_client_publish(client,
    "ospp/v1/stations/{{ $station->station_id }}/to-server",
    boot, 0, 1, 0);
```

### Python — `resources/views/components/snippets/python.blade.php`

```python
import paho.mqtt.client as mqtt
import json, time

client = mqtt.Client()
client.username_pw_set("{{ $station->mqtt_username }}", "YOUR_PASSWORD")
client.tls_set()
client.connect("csms-sandbox.ospp-standard.org", 8883)

station_id = "{{ $station->station_id }}"
client.subscribe(f"ospp/v1/stations/{station_id}/to-station")

boot = {
    "action": "BootNotification",
    "messageId": "msg_001",
    "messageType": "Request",
    "source": "Station",
    "protocolVersion": "0.1.0",
    "timestamp": "2026-01-01T00:00:00.000Z",
    "payload": {
        "stationModel": "MyStation",
        "stationVendor": "MyCompany",
        "firmwareVersion": "1.0.0",
        "bayCount": 1
    }
}

client.publish(f"ospp/v1/stations/{station_id}/to-server",
    json.dumps(boot), qos=1)
client.loop_forever()
```

### JavaScript — `resources/views/components/snippets/javascript.blade.php`

```javascript
const mqtt = require('mqtt');

const client = mqtt.connect('mqtts://csms-sandbox.ospp-standard.org:8883', {
    username: '{{ $station->mqtt_username }}',
    password: 'YOUR_PASSWORD',
});

const stationId = '{{ $station->station_id }}';

client.on('connect', () => {
    client.subscribe(`ospp/v1/stations/${stationId}/to-station`);

    client.publish(`ospp/v1/stations/${stationId}/to-server`,
        JSON.stringify({
            action: 'BootNotification',
            messageId: 'msg_001',
            messageType: 'Request',
            source: 'Station',
            protocolVersion: '0.1.0',
            timestamp: new Date().toISOString().replace(/(\.\d{3})\d*Z/, '$1Z'),
            payload: {
                stationModel: 'MyStation',
                stationVendor: 'MyCompany',
                firmwareVersion: '1.0.0',
                bayCount: 1,
            }
        }),
        { qos: 1 }
    );
});

client.on('message', (topic, message) => {
    console.log('Received:', JSON.parse(message.toString()));
});
```

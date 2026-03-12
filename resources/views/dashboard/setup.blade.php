@extends('layouts.app')
@section('title', 'Setup')
@section('content')

<div class="max-w-3xl">
    <h2 class="text-2xl font-bold mb-6">MQTT Connection</h2>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-500">Host</label>
                <p class="font-mono text-sm">{{ config('sandbox.mqtt_public_host', 'csms-sandbox.ospp-standard.org') }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Port (TLS)</label>
                <p class="font-mono text-sm">{{ config('mqtt.tls_port', 8883) }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Username</label>
                <p class="font-mono text-sm">{{ $station->mqtt_username }}</p>
            </div>
            <div x-data="{ show: false }">
                <label class="text-sm text-gray-500">Password</label>
                <div class="flex items-center space-x-2">
                    <p class="font-mono text-sm" x-text="show ? '{{ $mqttPassword }}' : '********'"></p>
                    <button @click="show = !show" class="text-xs text-ospp-600 hover:underline" x-text="show ? 'Hide' : 'Show'"></button>
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
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold mb-3">MQTT Topics</h3>
        <div class="space-y-2 font-mono text-sm">
            <div>
                <span class="text-gray-500">Publish to:</span>
                <span>{{ config('mqtt.topic_prefix') }}/{{ $station->station_id }}/{{ config('mqtt.to_server_suffix') }}</span>
            </div>
            <div>
                <span class="text-gray-500">Subscribe to:</span>
                <span>{{ config('mqtt.topic_prefix') }}/{{ $station->station_id }}/{{ config('mqtt.to_station_suffix') }}</span>
            </div>
        </div>
    </div>

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

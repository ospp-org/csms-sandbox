@extends('layouts.app')
@section('title', 'Setup')
@section('content')

<div class="max-w-3xl">
    <div class="flex items-center space-x-4 mb-6">
        <h2 class="text-2xl font-bold">MQTT Connection</h2>
        @if(isset($stations) && $stations->count() > 1)
        <select onchange="window.location.href='/dashboard/setup?station='+this.value" class="border rounded px-2 py-1 text-sm">
            @foreach($stations as $s)
            <option value="{{ $s->station_id }}" {{ $station->station_id === $s->station_id ? 'selected' : '' }}>{{ $s->station_id }}</option>
            @endforeach
        </select>
        @endif
    </div>

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
                <p class="font-mono text-sm">{{ $tenant->protocol_version }}</p>
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

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="font-semibold mb-3">Station Certificate (mTLS)</h3>

        @if(session('success'))
            <div class="bg-green-50 text-green-700 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 text-red-700 px-4 py-2 rounded mb-4 text-sm">{{ session('error') }}</div>
        @endif

        @if($station->station_cert)
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="text-sm text-gray-500">Status</label>
                    <p class="text-sm font-medium text-green-600">Issued</p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">Expires</label>
                    <p class="font-mono text-sm">{{ $station->cert_expires_at?->format('Y-m-d') }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">Issued</label>
                    <p class="font-mono text-sm">{{ $station->cert_issued_at?->format('Y-m-d H:i') }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">CN</label>
                    <p class="font-mono text-sm">{{ $station->station_id }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mb-4">
                <a href="/dashboard/certificates/ca" class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-sm">
                    CA Chain
                </a>
                <a href="/dashboard/certificates/cert" class="inline-flex items-center px-3 py-1.5 bg-ospp-500 hover:bg-ospp-600 text-white rounded text-sm">
                    Certificate
                </a>
                <a href="/dashboard/certificates/key" class="inline-flex items-center px-3 py-1.5 bg-ospp-500 hover:bg-ospp-600 text-white rounded text-sm">
                    Private Key
                </a>
            </div>

            <form action="/dashboard/certificates/regenerate" method="POST" class="mb-4" onsubmit="return confirm('Regenerate certificate? The current certificate will be revoked.')">
                @csrf
                <button type="submit" class="text-sm text-red-600 hover:underline">Regenerate Certificate</button>
            </form>

            <div class="bg-yellow-50 border border-yellow-200 rounded p-3 text-xs text-yellow-800">
                <strong>Keep your private key secure.</strong> Do not share it or commit it to version control.
            </div>
        @else
            <div class="text-sm text-gray-500 mb-4">
                <p>Certificate not yet generated. PKI must be configured on the server.</p>
            </div>
            <a href="/dashboard/certificates/ca" class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-sm">
                Download CA Chain
            </a>
        @endif

        <div class="mt-4 bg-blue-50 border border-blue-200 rounded p-3 text-xs text-blue-800">
            <strong>SANDBOX DEVIATION:</strong> Private key is generated server-side for testing purposes only.
            In production OSPP deployments, private keys are generated on-device and never leave the station hardware.
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

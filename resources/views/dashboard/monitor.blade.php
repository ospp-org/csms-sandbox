@extends('layouts.app')
@section('title', 'Live Monitor')
@section('content')

<div x-data="liveMonitor()" x-init="connect()">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold">Live Messages</h2>
        <div class="flex space-x-2">
            <span class="text-sm text-gray-500 self-center" x-text="messages.length + ' messages'"></span>
            <button @click="togglePause()" class="px-3 py-1 rounded text-sm"
                :class="paused ? 'bg-green-500 text-white' : 'bg-yellow-500 text-white'"
                x-text="paused ? 'Resume' : 'Pause'">
            </button>
            <button @click="clear()" class="px-3 py-1 rounded text-sm bg-gray-200 text-gray-700">Clear</button>
        </div>
    </div>

    <div class="flex space-x-4 mb-4">
        <select x-model="filterAction" class="text-sm border rounded px-2 py-1">
            <option value="">All actions</option>
            <template x-for="action in availableActions">
                <option :value="action" x-text="action"></option>
            </template>
        </select>
        <select x-model="filterDirection" class="text-sm border rounded px-2 py-1">
            <option value="">Both</option>
            <option value="inbound">Inbound</option>
            <option value="outbound">Outbound</option>
        </select>
        <select x-model="filterValid" class="text-sm border rounded px-2 py-1">
            <option value="">All</option>
            <option value="true">Valid</option>
            <option value="false">Invalid</option>
        </select>
    </div>

    <div class="space-y-2 max-h-[calc(100vh-200px)] overflow-y-auto">
        <template x-for="msg in filteredMessages" :key="msg.id">
            <div class="bg-white rounded-lg shadow-sm border p-3 cursor-pointer"
                 @click="msg.expanded = !msg.expanded"
                 :class="msg.schema_valid === false ? 'border-red-200' : 'border-gray-100'">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <span class="text-xs text-gray-400 font-mono" x-text="formatTime(msg.created_at)"></span>
                        <span class="text-xs px-1.5 py-0.5 rounded font-medium"
                              :class="msg.direction === 'inbound' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'"
                              x-text="msg.direction === 'inbound' ? 'IN' : 'OUT'"></span>
                        <span class="text-sm font-medium" x-text="msg.action"></span>
                    </div>
                    <span class="text-xs px-1.5 py-0.5 rounded"
                          :class="msg.schema_valid === false ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'"
                          x-text="msg.schema_valid === false ? 'Invalid' : 'Valid'"></span>
                </div>
                <div x-show="msg.expanded" x-transition class="mt-2">
                    <pre class="bg-gray-50 p-2 rounded text-xs overflow-x-auto" x-text="JSON.stringify(msg.payload, null, 2)"></pre>
                    <template x-if="msg.validation_errors && msg.validation_errors.length > 0">
                        <div class="mt-2 bg-red-50 p-2 rounded">
                            <p class="text-xs font-medium text-red-700 mb-1">Validation Errors:</p>
                            <template x-for="err in msg.validation_errors">
                                <p class="text-xs text-red-600"><span class="font-mono" x-text="err.field || err.path"></span>: <span x-text="err.message"></span></p>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>
        <div x-show="messages.length === 0" class="text-center py-12 text-gray-400">
            <p class="text-lg">No messages yet</p>
            <p class="text-sm">Connect your station to see messages here</p>
        </div>
    </div>
</div>

@push('scripts')
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

        stationId: '{{ auth()->user()->station?->station_id }}',

        connect() {
            if (!window.Echo || !this.stationId) return;

            window.Echo.private('station.' + this.stationId)
                .listen('MessageReceived', (e) => {
                    if (!this.paused) this.addMessage(e.message);
                })
                .listen('MessageSent', (e) => {
                    if (!this.paused) this.addMessage(e.message);
                });
        },

        addMessage(msg) {
            msg.expanded = false;
            this.messages.unshift(msg);
            if (this.messages.length > 1000) this.messages.pop();
        },

        togglePause() { this.paused = !this.paused; },
        clear() { this.messages = []; },
        formatTime(ts) {
            if (!ts) return '';
            const d = new Date(ts);
            return d.toLocaleTimeString('en-US', { hour12: false });
        }
    };
}
</script>
@endpush

@endsection

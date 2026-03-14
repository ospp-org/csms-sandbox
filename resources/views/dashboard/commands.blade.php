@extends('layouts.app')
@section('title', 'Command Center')
@section('content')

<div x-data="commandCenter()">
    <h2 class="text-2xl font-bold mb-6">Command Center</h2>

    <div class="grid grid-cols-3 gap-6">
        <div class="col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="mb-4">
                    <label class="block text-sm text-gray-600 mb-1">Action</label>
                    <select x-model="selectedAction" @change="loadSchema()" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="">Select a command...</option>
                        <option value="StartService">StartService</option>
                        <option value="StopService">StopService</option>
                        <option value="ReserveBay">ReserveBay</option>
                        <option value="CancelReservation">CancelReservation</option>
                        <option value="Reset">Reset</option>
                        <option value="ChangeConfiguration">ChangeConfiguration</option>
                        <option value="GetConfiguration">GetConfiguration</option>
                        <option value="UpdateFirmware">UpdateFirmware</option>
                        <option value="GetDiagnostics">GetDiagnostics</option>
                        <option value="SetMaintenanceMode">SetMaintenanceMode</option>
                        <option value="TriggerMessage">TriggerMessage</option>
                        <option value="UpdateServiceCatalog">UpdateServiceCatalog</option>
                        <option value="CertificateInstall">CertificateInstall</option>
                        <option value="TriggerCertificateRenewal">TriggerCertificateRenewal</option>
                    </select>
                </div>

                <template x-if="schema">
                    <div class="space-y-3">
                        <template x-for="(prop, key) in schema.properties || {}" :key="key">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">
                                    <span x-text="key"></span>
                                    <span x-show="(schema.required || []).includes(key)" class="text-red-400">*</span>
                                </label>
                                <template x-if="prop.enum">
                                    <select x-model="formData[key]" class="w-full border rounded px-3 py-2 text-sm">
                                        <template x-for="opt in prop.enum">
                                            <option :value="opt" x-text="opt"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="!prop.enum && prop.type === 'boolean'">
                                    <select x-model="formData[key]" class="w-full border rounded px-3 py-2 text-sm">
                                        <option :value="true">true</option>
                                        <option :value="false">false</option>
                                    </select>
                                </template>
                                <template x-if="!prop.enum && (prop.type === 'number' || prop.type === 'integer')">
                                    <input type="number" x-model.number="formData[key]" class="w-full border rounded px-3 py-2 text-sm">
                                </template>
                                <template x-if="!prop.enum && prop.type === 'object'">
                                    <textarea x-model="formData[key]" rows="3" placeholder="{}" class="w-full border rounded px-3 py-2 text-sm font-mono"></textarea>
                                </template>
                                <template x-if="!prop.enum && prop.type === 'array'">
                                    <textarea x-model="formData[key]" rows="3" placeholder="[]" class="w-full border rounded px-3 py-2 text-sm font-mono"></textarea>
                                </template>
                                <template x-if="!prop.enum && (prop.type === 'string' || (!prop.type && !prop.enum))">
                                    <input type="text" x-model="formData[key]" :placeholder="prop.description || ''" class="w-full border rounded px-3 py-2 text-sm">
                                </template>
                            </div>
                        </template>

                        <button @click="send()" :disabled="sending"
                            class="bg-ospp-500 text-white px-4 py-2 rounded text-sm font-medium hover:bg-ospp-600 disabled:opacity-50"
                            x-text="sending ? 'Sending...' : 'Send Command'">
                        </button>
                    </div>
                </template>

                <template x-if="result">
                    <div class="mt-4 p-3 rounded text-sm" :class="result.error ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'">
                        <pre x-text="JSON.stringify(result, null, 2)"></pre>
                    </div>
                </template>
            </div>
        </div>

        <div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-semibold mb-3">Recent Commands</h3>
                <div class="space-y-2">
                    <template x-for="cmd in recentCommands" :key="cmd.time">
                        <div class="flex justify-between items-center text-sm border-b pb-2">
                            <span x-text="cmd.action"></span>
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                  :class="cmd.status === 'sent' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                  x-text="cmd.status"></span>
                        </div>
                    </template>
                    <p x-show="recentCommands.length === 0" class="text-sm text-gray-400">No commands sent yet</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function commandCenter() {
    return {
        selectedAction: '',
        schema: null,
        formData: {},
        sending: false,
        result: null,
        recentCommands: [],

        async loadSchema() {
            if (!this.selectedAction) { this.schema = null; return; }
            this.result = null;
            try {
                const resp = await fetch('/dashboard/commands/' + this.selectedAction + '/schema');
                const data = await resp.json();
                this.schema = data.schema || {};
                this.formData = this.buildDefaults(this.schema);
            } catch(e) { this.schema = null; }
        },

        buildDefaults(schema) {
            const data = {};
            for (const [key, prop] of Object.entries(schema.properties || {})) {
                if (prop.default !== undefined) data[key] = prop.default;
                else if (prop.enum) data[key] = prop.enum[0];
                else if (prop.type === 'number' || prop.type === 'integer') data[key] = 0;
                else if (prop.type === 'boolean') data[key] = false;
                else if (prop.type === 'object') data[key] = '{}';
                else if (prop.type === 'array') data[key] = '[]';
                else data[key] = '';
            }
            return data;
        },

        preparePayload() {
            const payload = {};
            for (const [key, val] of Object.entries(this.formData)) {
                const prop = this.schema?.properties?.[key];
                if (prop && (prop.type === 'object' || prop.type === 'array')) {
                    try { payload[key] = JSON.parse(val); } catch { payload[key] = val; }
                } else {
                    payload[key] = val;
                }
            }
            return payload;
        },

        async send() {
            this.sending = true;
            this.result = null;
            try {
                const resp = await fetch('/dashboard/commands/' + this.selectedAction, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.preparePayload())
                });
                this.result = await resp.json();
                this.recentCommands.unshift({
                    action: this.selectedAction,
                    status: this.result.status || this.result.error || 'unknown',
                    time: new Date().toISOString(),
                });
            } catch(e) {
                this.result = { error: 'Request failed' };
            }
            this.sending = false;
        }
    };
}
</script>
@endpush

@endsection

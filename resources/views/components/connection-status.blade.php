@props(['station'])

<div x-data="connectionStatus()" class="flex items-center space-x-2">
    <span class="w-2 h-2 rounded-full" :class="connected ? 'bg-green-500' : 'bg-red-500'"></span>
    <span class="text-sm text-gray-600" x-text="connected ? 'Station Connected' : 'Station Disconnected'"></span>
</div>

@pushOnce('scripts')
<script>
function connectionStatus() {
    return {
        connected: {{ $station->is_connected ? 'true' : 'false' }},
        init() {
            if (!window.Echo) return;
            window.Echo.private('station.{{ $station->station_id }}')
                .listen('StationConnected', () => { this.connected = true; })
                .listen('StationDisconnected', () => { this.connected = false; });
        }
    };
}
</script>
@endPushOnce

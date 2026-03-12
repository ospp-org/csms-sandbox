@php $station = auth()->user()->station; @endphp
<aside class="w-64 bg-white border-r border-gray-200 flex flex-col">
    <div class="p-4 border-b">
        <h1 class="text-lg font-bold text-ospp-700">OSPP Sandbox</h1>
        <p class="text-xs text-gray-500">Protocol Testing Environment</p>
    </div>

    <nav class="flex-1 p-4 space-y-1">
        <a href="/dashboard/setup" class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('dashboard/setup') ? 'bg-ospp-50 text-ospp-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">Setup</a>
        <a href="/dashboard/monitor" class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('dashboard/monitor') ? 'bg-ospp-50 text-ospp-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">Live Monitor</a>
        <a href="/dashboard/commands" class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('dashboard/commands') ? 'bg-ospp-50 text-ospp-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">Command Center</a>
        <a href="/dashboard/conformance" class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('dashboard/conformance') ? 'bg-ospp-50 text-ospp-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">Conformance</a>
        <a href="/dashboard/history" class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('dashboard/history') ? 'bg-ospp-50 text-ospp-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">History</a>
        <a href="/dashboard/settings" class="flex items-center px-3 py-2 rounded-lg text-sm {{ request()->is('dashboard/settings') ? 'bg-ospp-50 text-ospp-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">Settings</a>
    </nav>

    <div class="p-4 border-t">
        @if($station)
        <x-connection-status :station="$station" />
        <p class="text-xs text-gray-400 mt-1">{{ $station->station_id }}</p>
        @else
        <div class="flex items-center space-x-2">
            <span class="w-2 h-2 rounded-full bg-red-500"></span>
            <span class="text-sm text-gray-600">No Station</span>
        </div>
        @endif
    </div>

    <div class="p-4 border-t">
        <p class="text-sm text-gray-600">{{ auth()->user()->name }}</p>
        <form method="POST" action="/logout" class="mt-1">
            @csrf
            <button type="submit" class="text-xs text-red-600 hover:underline">Logout</button>
        </form>
    </div>
</aside>

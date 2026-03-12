@extends('layouts.app')
@section('title', 'Settings')
@section('content')

<div class="max-w-lg">
    <h2 class="text-2xl font-bold mb-6">Settings</h2>

    @if(session('success'))
    <div class="bg-green-50 text-green-700 p-3 rounded mb-4 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="/dashboard/settings" class="bg-white rounded-lg shadow p-6 space-y-4">
        @csrf
        @method('PATCH')

        <div>
            <label class="block text-sm text-gray-600 mb-1">Protocol Version</label>
            <select name="protocol_version" class="w-full border rounded px-3 py-2 text-sm">
                <option value="0.1.0" {{ $tenant->protocol_version === '0.1.0' ? 'selected' : '' }}>0.1.0</option>
            </select>
            <p class="text-xs text-yellow-600 mt-1">Changing protocol version will reset conformance results.</p>
        </div>

        <div>
            <label class="block text-sm text-gray-600 mb-1">Validation Mode</label>
            <select name="validation_mode" class="w-full border rounded px-3 py-2 text-sm">
                <option value="strict" {{ $tenant->validation_mode === 'strict' ? 'selected' : '' }}>Strict</option>
                <option value="lenient" {{ $tenant->validation_mode === 'lenient' ? 'selected' : '' }}>Lenient</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">Strict: rejects invalid messages. Lenient: processes anyway.</p>
        </div>

        <button type="submit" class="bg-ospp-500 text-white px-4 py-2 rounded text-sm font-medium hover:bg-ospp-600">Save Settings</button>
    </form>
</div>

@endsection

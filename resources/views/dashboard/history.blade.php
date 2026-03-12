@extends('layouts.app')
@section('title', 'History')
@section('content')

<div>
    <h2 class="text-2xl font-bold mb-6">Message History</h2>

    <form method="GET" action="/dashboard/history" class="flex space-x-4 mb-4">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search messageId..." class="text-sm border rounded px-3 py-1.5 w-64">
        <select name="action" class="text-sm border rounded px-2 py-1">
            <option value="">All actions</option>
            @foreach(['BootNotification','Heartbeat','StatusNotification','MeterValues','DataTransfer','SecurityEvent','SignCertificate','StartServiceResponse','StopServiceResponse','ReserveBayResponse','CancelReservationResponse','ResetResponse'] as $action)
            <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
            @endforeach
        </select>
        <select name="direction" class="text-sm border rounded px-2 py-1">
            <option value="">Both</option>
            <option value="inbound" {{ request('direction') === 'inbound' ? 'selected' : '' }}>Inbound</option>
            <option value="outbound" {{ request('direction') === 'outbound' ? 'selected' : '' }}>Outbound</option>
        </select>
        <select name="valid" class="text-sm border rounded px-2 py-1">
            <option value="">All</option>
            <option value="1" {{ request('valid') === '1' ? 'selected' : '' }}>Valid</option>
            <option value="0" {{ request('valid') === '0' ? 'selected' : '' }}>Invalid</option>
        </select>
        <button type="submit" class="bg-ospp-500 text-white px-3 py-1 rounded text-sm">Filter</button>
        <a href="/dashboard/history" class="text-sm text-gray-500 self-center hover:underline">Clear</a>
    </form>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Time</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Dir</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Action</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Message ID</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Valid</th>
                </tr>
            </thead>
            <tbody x-data="{ expanded: null }">
                @forelse($messages as $index => $msg)
                <x-message-row :message="$msg" :index="$index" />
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-400">No messages found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $messages->links() }}
    </div>
</div>

@endsection

@extends('layouts.app')
@section('title', 'Conformance')
@section('content')

<div>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Conformance Report</h2>
        <div class="flex space-x-2">
            <a href="/dashboard/conformance/export/json" class="px-3 py-1 rounded text-sm bg-gray-200 text-gray-700 hover:bg-gray-300">Export JSON</a>
            <a href="/dashboard/conformance/export/pdf" class="px-3 py-1 rounded text-sm bg-gray-200 text-gray-700 hover:bg-gray-300">Export PDF</a>
            <form method="POST" action="/dashboard/conformance/reset" class="inline">
                @csrf
                <button type="submit" class="px-3 py-1 rounded text-sm bg-red-50 text-red-600 hover:bg-red-100" onclick="return confirm('Reset all conformance results?')">Reset</button>
            </form>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 text-green-700 p-3 rounded mb-4 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <canvas id="scoreChart" width="200" height="200"></canvas>
            <p class="text-sm text-gray-500 mt-3">Tested: {{ $report->totalTested }} / {{ $report->totalTested + $report->notTested }} actions</p>
            <p class="text-2xl font-bold mt-1">Score: {{ $report->passed }} / {{ $report->totalTested }} passed ({{ $report->percentage }}%)</p>
        </div>

        <div class="col-span-2 bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold mb-3">Categories</h3>
            <div class="space-y-3">
                @foreach($report->categories as $name => $cat)
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span>{{ ucfirst(str_replace('_', ' ', $name)) }}</span>
                        <span class="text-gray-500">{{ $cat['passed'] }}/{{ $cat['total'] }} ({{ $cat['percentage'] }}%)</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-ospp-500 h-2 rounded-full" style="width: {{ $cat['percentage'] }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="p-6">
            <h3 class="font-semibold mb-3">Action Results</h3>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-t bg-gray-50">
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Action</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Last Tested</th>
                </tr>
            </thead>
            <tbody x-data="{ expanded: null }">
                @foreach($report->results as $index => $result)
                <tr class="border-t hover:bg-gray-50 cursor-pointer" @click="expanded = expanded === {{ $index }} ? null : {{ $index }}">
                    <td class="px-6 py-3 text-sm font-medium">{{ $result['action'] }}</td>
                    <td class="px-6 py-3 text-sm text-gray-500">{{ ucfirst(str_replace('_', ' ', $actionToCategory[$result['action']] ?? 'unknown')) }}</td>
                    <td class="px-6 py-3"><x-conformance-badge :status="$result['status']" /></td>
                    <td class="px-6 py-3 text-sm text-gray-500">{{ $result['last_tested_at'] ? \Carbon\Carbon::parse($result['last_tested_at'])->format('Y-m-d H:i') : '-' }}</td>
                </tr>
                <tr x-show="expanded === {{ $index }}" x-transition class="border-t bg-gray-50">
                    <td colspan="4" class="px-6 py-3">
                        @if(!empty($result['error_details']))
                        <div class="mb-2">
                            <p class="text-xs font-medium text-red-700 mb-1">Schema Errors:</p>
                            @foreach($result['error_details'] as $err)
                            <p class="text-xs text-red-600">{{ $err['path'] ?? '' }}: {{ $err['message'] ?? '' }}</p>
                            @endforeach
                        </div>
                        @endif
                        @if(!empty($result['behavior_checks']))
                        <div>
                            <p class="text-xs font-medium text-gray-700 mb-1">Behavior Checks:</p>
                            @foreach($result['behavior_checks'] as $check)
                            <p class="text-xs {{ $check['passed'] ? 'text-green-600' : 'text-red-600' }}">
                                {{ $check['passed'] ? 'PASS' : 'FAIL' }} {{ $check['rule'] }}{{ $check['detail'] ? ': ' . $check['detail'] : '' }}
                            </p>
                            @endforeach
                        </div>
                        @endif
                        @if(empty($result['error_details']) && empty($result['behavior_checks']))
                        <p class="text-xs text-gray-400">No details available</p>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.x.x/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('scoreChart'), {
    type: 'doughnut',
    data: {
        labels: ['Passed', 'Failed', 'Partial', 'Not Tested'],
        datasets: [{
            data: [{{ $report->passed }}, {{ $report->failed }}, {{ $report->partial }}, {{ $report->notTested }}],
            backgroundColor: ['#22c55e', '#ef4444', '#f59e0b', '#e5e7eb'],
        }]
    },
    options: {
        cutout: '70%',
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
    }
});
</script>
@endpush

@endsection

@props(['status'])

@php
$classes = match($status) {
    'passed' => 'bg-green-100 text-green-800',
    'failed' => 'bg-red-100 text-red-800',
    'partial' => 'bg-yellow-100 text-yellow-800',
    default => 'bg-gray-100 text-gray-500',
};
$label = match($status) {
    'passed' => 'Passed',
    'failed' => 'Failed',
    'partial' => 'Partial',
    'not_tested' => 'Not Tested',
    default => ucfirst(str_replace('_', ' ', $status)),
};
@endphp

<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $classes }}">
    {{ $label }}
</span>

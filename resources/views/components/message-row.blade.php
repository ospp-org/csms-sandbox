@props(['message', 'index'])

<tr class="border-t hover:bg-gray-50 cursor-pointer" @click="expanded = expanded === {{ $index }} ? null : {{ $index }}">
    <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ $message->created_at?->format('Y-m-d H:i:s') }}</td>
    <td class="px-4 py-3">
        <span class="text-xs px-1.5 py-0.5 rounded font-medium {{ $message->direction === 'inbound' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">{{ $message->direction === 'inbound' ? 'IN' : 'OUT' }}</span>
    </td>
    <td class="px-4 py-3 text-sm font-medium">{{ $message->action }}</td>
    <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ $message->message_id }}</td>
    <td class="px-4 py-3">
        <span class="text-xs px-1.5 py-0.5 rounded {{ $message->schema_valid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $message->schema_valid ? 'Valid' : 'Invalid' }}</span>
    </td>
</tr>
<tr x-show="expanded === {{ $index }}" x-transition class="border-t bg-gray-50">
    <td colspan="5" class="px-4 py-3">
        <pre class="bg-gray-100 p-2 rounded text-xs overflow-x-auto">{{ json_encode($message->payload, JSON_PRETTY_PRINT) }}</pre>
        @if($message->validation_errors)
        <div class="mt-2 bg-red-50 p-2 rounded">
            <p class="text-xs font-medium text-red-700 mb-1">Errors:</p>
            @foreach($message->validation_errors as $err)
            <p class="text-xs text-red-600">{{ $err['field'] ?? $err['path'] ?? '' }}: {{ $err['message'] ?? '' }}</p>
            @endforeach
        </div>
        @endif
    </td>
</tr>

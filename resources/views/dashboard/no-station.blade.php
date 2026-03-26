@extends('layouts.app')
@section('title', 'No Station')
@section('content')

<div class="max-w-lg mx-auto mt-12">
    <div class="bg-white rounded-lg shadow p-8 text-center">
        <div class="text-6xl mb-4">📡</div>
        <h2 class="text-2xl font-bold mb-3">No Station Configured</h2>
        <p class="text-gray-600 mb-6">
            Your account doesn't have a station yet.
            Stations are automatically created during registration.
            If you're seeing this page, your account may have been created outside the normal registration flow.
        </p>
        <div class="bg-blue-50 border border-blue-200 rounded p-4 text-sm text-blue-800">
            <strong>Need help?</strong> Contact support at
            <a href="mailto:support@ospp-standard.org" class="underline font-medium">support@ospp-standard.org</a>
        </div>
    </div>
</div>

@endsection

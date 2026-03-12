@extends('layouts.app')
@section('title', 'Login')
@section('content')
<div class="min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-md">
        <h1 class="text-2xl font-bold text-ospp-700 mb-6 text-center">OSPP Sandbox</h1>

        @if($errors->any())
        <div class="bg-red-50 text-red-700 p-3 rounded mb-4 text-sm">
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="/login">
            @csrf
            <div class="mb-4">
                <label class="block text-sm text-gray-600 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ospp-500">
            </div>
            <div class="mb-6">
                <label class="block text-sm text-gray-600 mb-1">Password</label>
                <input type="password" name="password" required
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ospp-500">
            </div>
            <button type="submit" class="w-full bg-ospp-500 text-white py-2 rounded font-medium hover:bg-ospp-600">Login</button>
        </form>

        <p class="text-center text-sm text-gray-500 mt-4">
            Don't have an account? <a href="/register" class="text-ospp-600 hover:underline">Register</a>
        </p>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Register')
@section('content')
<div class="min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-md">
        <h1 class="text-2xl font-bold text-ospp-700 mb-6 text-center">Create Account</h1>

        @if($errors->any())
        <div class="bg-red-50 text-red-700 p-3 rounded mb-4 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="/register">
            @csrf
            <div class="mb-4">
                <label class="block text-sm text-gray-600 mb-1">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ospp-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm text-gray-600 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ospp-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm text-gray-600 mb-1">Password</label>
                <input type="password" name="password" required
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ospp-500">
            </div>
            <div class="mb-6">
                <label class="block text-sm text-gray-600 mb-1">Confirm Password</label>
                <input type="password" name="password_confirmation" required
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ospp-500">
            </div>
            <button type="submit" class="w-full bg-ospp-500 text-white py-2 rounded font-medium hover:bg-ospp-600">Register</button>
        </form>

        <p class="text-center text-sm text-gray-500 mt-4">
            Already have an account? <a href="/login" class="text-ospp-600 hover:underline">Login</a>
        </p>
    </div>
</div>
@endsection

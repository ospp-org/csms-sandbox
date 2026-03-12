<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CommandController;
use App\Http\Controllers\Api\ConformanceController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::prefix('v1/auth')->group(function (): void {
    Route::post('register', RegisterController::class);
    Route::post('login', LoginController::class);
});

// Authenticated routes
Route::prefix('v1')->middleware('auth:jwt')->group(function (): void {
    Route::post('auth/logout', LogoutController::class);

    Route::get('station', [StationController::class, 'show']);
    Route::post('station/regenerate-password', [StationController::class, 'regeneratePassword']);
    Route::get('station/status', [StationController::class, 'status']);

    Route::post('commands/{action}', [CommandController::class, 'send']);
    Route::get('commands/history', [CommandController::class, 'history']);
    Route::get('commands/{action}/schema', [CommandController::class, 'schema']);

    Route::get('conformance', [ConformanceController::class, 'index']);
    Route::get('conformance/export/pdf', [ConformanceController::class, 'exportPdf']);
    Route::get('conformance/export/json', [ConformanceController::class, 'exportJson']);
    Route::post('conformance/reset', [ConformanceController::class, 'reset']);
    Route::get('conformance/{action}', [ConformanceController::class, 'show']);
});

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CommandController;
use App\Http\Controllers\Api\ConformanceController;
use App\Http\Controllers\Api\SimulatorCertificateController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('v1/status', StatusController::class);

// Public auth routes
Route::prefix('v1/auth')->group(function (): void {
    Route::post('register', RegisterController::class)->middleware('throttle:auth');
    Route::post('login', LoginController::class)->middleware('throttle:auth');
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

    Route::get('stations/{stationId}', [StationController::class, 'showById']);
    Route::post('stations/{stationId}/force-reject', [StationController::class, 'forceReject']);
    Route::delete('stations/{stationId}/force-reject', [StationController::class, 'clearForceReject']);
    Route::post('stations/{stationId}/force-pending', [StationController::class, 'forcePending']);
    Route::delete('stations/{stationId}/force-pending', [StationController::class, 'clearForcePending']);
    Route::post('stations/{stationId}/trigger-command', [StationController::class, 'triggerCommand']);
    Route::post('stations/{stationId}/trigger-data-transfer', [StationController::class, 'triggerDataTransfer']);

    Route::get('simulator/certificates', SimulatorCertificateController::class);
});

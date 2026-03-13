<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\Mqtt\MqttAclController;
use App\Http\Controllers\Mqtt\MqttAuthController;
use App\Http\Controllers\Mqtt\MqttWebhookController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/health', HealthController::class);

// Auth (guest only)
Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
});

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Dashboard (auth required)
Route::prefix('dashboard')->middleware('auth')->group(function (): void {
    Route::get('setup', [DashboardController::class, 'setup'])->name('dashboard.setup');
    Route::get('monitor', [DashboardController::class, 'monitor'])->name('dashboard.monitor');
    Route::get('commands', [DashboardController::class, 'commands'])->name('dashboard.commands');
    Route::post('commands/{action}', [DashboardController::class, 'sendCommand']);
    Route::get('commands/{action}/schema', [DashboardController::class, 'commandSchema']);
    Route::get('conformance', [DashboardController::class, 'conformance'])->name('dashboard.conformance');
    Route::get('conformance/export/pdf', [DashboardController::class, 'exportPdf']);
    Route::get('conformance/export/json', [DashboardController::class, 'exportJson']);
    Route::post('conformance/reset', [DashboardController::class, 'resetConformance']);
    Route::get('history', [DashboardController::class, 'history'])->name('dashboard.history');
    Route::get('settings', [DashboardController::class, 'settings'])->name('dashboard.settings');
    Route::patch('settings', [DashboardController::class, 'updateSettings']);
});

// Internal MQTT endpoints (EMQX only)
// Protection: nginx restricts /internal/ to Docker network IPs (172.16.0.0/12, 10.0.0.0/8).
// The webhook route has additional verify-emqx middleware for defense-in-depth.
// Auth/ACL endpoints are called by EMQX on every MQTT connection — they validate
// station credentials and tenant ownership directly, no shared secret needed.
Route::prefix('internal/mqtt')->group(function (): void {
    Route::post('auth', MqttAuthController::class);
    Route::post('acl', MqttAclController::class);
    Route::post('webhook', MqttWebhookController::class)->middleware('verify-emqx');
});

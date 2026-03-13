<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\JwtGuard;
use App\Services\JwtService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtService::class);
    }

    public function boot(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Auth::extend('jwt', function (Application $app, string $name, array $config) {
            $provider = Auth::createUserProvider($config['provider']);

            return new JwtGuard(
                jwt: $app->make(JwtService::class),
                provider: $provider,
                request: $app->make('request'),
            );
        });
    }
}

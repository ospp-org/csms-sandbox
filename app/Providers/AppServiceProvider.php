<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\JwtGuard;
use App\Services\JwtService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtService::class);
    }

    public function boot(): void
    {
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

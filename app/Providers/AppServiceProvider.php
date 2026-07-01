<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('auth-login', fn (Request $request): Limit => Limit::perMinute(
            (int) config('feed.rate_limits.login_per_minute', 10),
        )->by($request->ip()));

        RateLimiter::for('api-read', fn (Request $request): Limit => Limit::perMinute(
            (int) config('feed.rate_limits.read_per_minute', 120),
        )->by((string) ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('api-write', fn (Request $request): Limit => Limit::perMinute(
            (int) config('feed.rate_limits.write_per_minute', 30),
        )->by((string) ($request->user()?->id ?? $request->ip())));
    }
}

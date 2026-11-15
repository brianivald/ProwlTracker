<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // En producción todas las URLs (assets, evidencias, formularios) van por HTTPS.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('node-api', function (Request $request) {
            return Limit::perMinute((int) config('prowltracker.node_api.rate_limit', 240))
                ->by($request->ip());
        });
    }
}

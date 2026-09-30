<?php

namespace App\Providers;

use App\Listeners\CreateDriverIdentity;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
        Event::listen(Registered::class, CreateDriverIdentity::class);

        RateLimiter::for('invite-create', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('invite-show', fn (Request $request) => Limit::perMinute(30)
            ->by($request->ip()));

        RateLimiter::for('invite-accept', fn (Request $request) => Limit::perMinute(5)
            ->by($request->user()?->id ?: $request->ip()));
    }
}

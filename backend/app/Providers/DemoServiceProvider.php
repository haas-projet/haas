<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

final class DemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes();
        $this->callAfterResolving(RateLimiter::class, static function (RateLimiter $limiter): void {
            $limiter->for('demo-orders', static fn (Request $request): Limit => Limit::perMinute(30)->by(hash('sha256', (string) $request->ip())));
        });
    }
}

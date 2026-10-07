<?php

namespace App\Providers;

use App\Support\Demo\DemoRuntimeGuard;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class DemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CommandStarting::class, static function (CommandStarting $event): void {
            if (! in_array($event->command, ['list', 'help', 'about', 'route:list', 'schedule:list', 'config:show', 'env'], true)) {
                app(DemoRuntimeGuard::class)->database();
            }
        });
        Model::preventSilentlyDiscardingAttributes();
        $this->callAfterResolving(RateLimiter::class, static function (RateLimiter $limiter): void {
            $limiter->for('demo-orders', static fn (Request $request): Limit => Limit::perMinute(30)->by(hash('sha256', (string) $request->ip())));
        });
    }
}

<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

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
        Model::preventSilentlyDiscardingAttributes();
        Sanctum::getAccessTokenFromRequestUsing(static fn (): string => '');
        $this->callAfterResolving(RateLimiter::class, static function (RateLimiter $limiter): void {
            $limiter->for('login', function (Request $request): array {
                $email = $request->input('email');
                $email = is_string($email) ? Str::lower(trim($email)) : '';
                $key = hash_hmac('sha256', $email.'|'.$request->ip(), (string) config('app.key'));

                return [Limit::perMinute(5)->by('credentials:'.$key), Limit::perMinute(20)->by('ip:'.$request->ip())];
            });
        });
    }
}

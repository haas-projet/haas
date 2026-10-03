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
            foreach (['password-link', 'password-reset'] as $name) {
                $limiter->for($name, static function (Request $request) use ($name): array {
                    $email = $request->input('email');
                    $key = hash_hmac('sha256', (is_string($email) ? Str::lower(trim($email)) : '').'|'.$request->ip(), (string) config('app.key'));

                    return [Limit::perMinute(5)->by($name.':'.$key), Limit::perMinute(20)->by($name.':ip:'.$request->ip())];
                });
            }
            $limiter->for('verification-send', static fn (Request $request): array => [
                Limit::perMinute(1)->by('minute:'.$request->user()?->getAuthIdentifier()),
                Limit::perHour(6)->by('hour:'.$request->user()?->getAuthIdentifier()),
            ]);
            $limiter->for('login', function (Request $request): array {
                $email = $request->input('email');
                $email = is_string($email) ? Str::lower(trim($email)) : '';
                $key = hash_hmac('sha256', $email.'|'.$request->ip(), (string) config('app.key'));

                return [Limit::perMinute(5)->by('credentials:'.$key), Limit::perMinute(20)->by('ip:'.$request->ip())];
            });
        });
    }
}

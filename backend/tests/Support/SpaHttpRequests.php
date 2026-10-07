<?php

namespace Tests\Support;

use App\Http\Middleware\RequireCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

trait SpaHttpRequests
{
    /** @var array<string, string> */
    private array $browserCookies = [];

    /** @var array<string, array<string, int>> */
    private array $browserCookieExpirations = [];

    private function configureSpa(string $sessionDriver = 'array'): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => 'https://api.haas.example.com',
            'spa.frontend_url' => 'https://app.haas.example.com',
            'cors.allowed_origins' => ['https://app.haas.example.com'],
            'sanctum.stateful' => ['app.haas.example.com', 'api.haas.example.com'],
            'session.driver' => $sessionDriver, 'session.domain' => '.haas.example.com',
            'session.secure' => true, 'session.cookie' => 'haas_session',
            'registration.terms_version' => 'fixture-v1',
        ]);
        $this->app->bind(RequireCsrfToken::class, EnforcedCsrfToken::class);
        Route::middleware(['api', 'auth:sanctum'])->get('/api/v1/session-fixture', fn (Request $request) => ['id' => $request->user()?->getAuthIdentifier()]);
        Route::middleware(['api', 'auth:sanctum'])->post('/api/v1/session-fixture', fn () => ['updated' => true]);
    }

    /** @param array<string, mixed> $body
     * @param  array<string, string>  $headers
     * @return TestResponse<Response>
     */
    private function browserRequest(string $method, string $path, array $body = [], array $headers = [], bool $sendXsrf = true, bool $withoutOrigin = false, bool $sendExpiredCookies = false): TestResponse
    {
        if (! $sendExpiredCookies) {
            foreach ($this->browserCookies as $name => $value) {
                $expires = $this->browserCookieExpirations[$name][$value] ?? 0;
                if ($expires !== 0 && $expires <= now()->getTimestamp()) {
                    unset($this->browserCookies[$name]);
                }
            }
        }
        // Chaque appel recharge garde et session depuis les vrais cookies reçus.
        Auth::forgetGuards();
        app()->forgetInstance('auth.driver');
        Auth::shouldUse('web');
        $arrayHandler = config('session.driver') === 'array' ? app('session')->driver()->getHandler() : null;
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        if ($arrayHandler !== null) {
            app('session')->driver()->setHandler($arrayHandler);
        }
        $headers = array_merge(['Accept' => 'application/json', 'Origin' => 'https://app.haas.example.com'], $headers);
        if ($withoutOrigin) {
            unset($headers['Origin']);
        }
        if ($sendXsrf && isset($this->browserCookies['XSRF-TOKEN'])) {
            $headers['X-XSRF-TOKEN'] ??= $this->browserCookies['XSRF-TOKEN'];
        }
        $response = $this->call($method, 'https://api.haas.example.com'.$path, [], $this->browserCookies, [],
            array_merge($this->transformHeadersToServerVars($headers), ['CONTENT_TYPE' => 'application/json']),
            json_encode($body, JSON_THROW_ON_ERROR));
        foreach ($response->headers->getCookies() as $cookie) {
            $name = $cookie->getName();
            $value = (string) $cookie->getValue();
            $expires = $cookie->getExpiresTime();
            // Indexer aussi par valeur préserve les jars sauvegardés/restaurés par les tests d'attaque.
            $this->browserCookieExpirations[$name][$value] = $expires;
            if ($expires !== 0 && $expires <= now()->getTimestamp()) {
                unset($this->browserCookies[$name]);
            } else {
                $this->browserCookies[$name] = $value;
            }
        }

        return $response;
    }
}

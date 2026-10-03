<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Identity\ResetAccountPassword;
use App\Notifications\Identity\VerifyAccountEmail;
use App\Support\Identity\AccountMailConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SpaHttpRequests;
use Tests\TestCase;

final class AccountMailValidationTest extends TestCase
{
    use SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa();
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_or_extra_inputs_are_rejected_without_sql(string $path, array $payload, string $field): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', $path, $payload)->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields')
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com');
    }

    /** @return iterable<string, array{string, array<string, mixed>, string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'missing email' => ['/forgot-password', [], 'email'];
        yield 'invalid email' => ['/forgot-password', ['email' => 'invalid'], 'email'];
        yield 'email array' => ['/forgot-password', ['email' => ['member@example.test']], 'email'];
        yield 'no redirect' => ['/forgot-password', ['email' => 'member@example.test', 'redirect' => 'https://untrusted.test'], 'redirect'];
        yield 'unknown null' => ['/forgot-password', ['email' => 'member@example.test', 'role' => null], 'role'];
        $valid = ['email' => 'member@example.test', 'token' => str_repeat('a', 64), 'password' => 'nouveau-mot-de-passe', 'password_confirmation' => 'nouveau-mot-de-passe'];
        foreach (['token' => 'bad', 'password' => 'court', 'password_confirmation' => 'different', 'email' => 'invalid', 'role' => 'admin'] as $field => $value) {
            yield 'reset '.$field => ['/reset-password', array_replace($valid, [$field => $value]), $field];
        }
        yield '128 max' => ['/reset-password', array_replace($valid, ['password' => str_repeat('é', 129)]), 'password'];
    }

    #[DataProvider('postPaths')]
    public function test_mail_commands_require_real_csrf(string $path): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', $path, sendXsrf: false)->assertStatus(419);
    }

    /** @return iterable<array{string}> */
    public static function postPaths(): iterable
    {
        yield ['/forgot-password'];
        yield ['/reset-password'];
        yield ['/email/verification-notification'];
    }

    public function test_public_password_commands_are_throttled_and_resend_requires_login(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        foreach (['/forgot-password', '/reset-password'] as $path) {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->browserRequest('POST', $path)->assertUnprocessable();
            }
            $this->browserRequest('POST', $path)->assertStatus(429)->assertHeader('Retry-After')
                ->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com');
        }
        $this->browserRequest('POST', '/email/verification-notification')->assertUnauthorized();
    }

    public function test_links_use_configured_origins_and_cannot_be_retargeted_by_host_or_query(): void
    {
        URL::setRequest(Request::create('https://untrusted.test'));
        $user = User::factory()->make();
        $user->id = 'd0648461-dc9d-4a0f-817b-3543dab3a28b';
        $url = (new VerifyAccountEmail($user->email))->toMail($user)->actionUrl;
        $this->assertStringStartsWith('https://api.haas.example.com/email/verify/', $url);
        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
        $this->assertFalse(URL::hasValidSignature(Request::create(str_replace('api.haas.example.com', 'app.haas.example.com', $url))));
        $this->assertFalse(URL::hasValidSignature(Request::create($url.'&redirect=https://untrusted.test')));
        $resetUrl = (new ResetAccountPassword($user->email, str_repeat('a', 64)))->toMail($user)->actionUrl;
        $this->assertStringStartsWith('https://app.haas.example.com/reset-password#', $resetUrl);
        $this->assertNull(parse_url($resetUrl, PHP_URL_QUERY));
    }

    public function test_mail_log_transport_is_rejected(): void
    {
        config(['mail.default' => 'log']);
        $this->expectException(LogicException::class);
        app(AccountMailConfiguration::class)->validate();
    }

    public function test_production_cannot_silently_discard_mail_to_array(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(LogicException::class);
        app(AccountMailConfiguration::class)->validate();
    }
}

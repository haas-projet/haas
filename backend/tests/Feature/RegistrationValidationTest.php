<?php

namespace Tests\Feature;

use App\Data\Identity\RegisterMemberData;
use App\Http\Middleware\RequireCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RegistrationFixtures;
use Tests\TestCase;

final class RegistrationValidationTest extends TestCase
{
    use RegistrationFixtures;

    #[DataProvider('invalidFields')]
    public function test_invalid_inputs_are_rejected_before_any_database_access(string $field, mixed $value): void
    {
        $payload = $this->registrationPayload();
        $payload[$field] = $value;
        $response = $this->postJson('/register', $payload)->assertUnprocessable();
        $response->assertJsonPath('error.code', 'VALIDATION_FAILED')->assertJsonStructure(['error' => ['fields' => [$field]]]);
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString('Mot-de-passe-fictif', $body);
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidFields(): iterable
    {
        yield 'short handle' => ['handle', 'ab'];
        yield 'long handle' => ['handle', str_repeat('é', 31)];
        yield 'control character' => ['handle', "Awa\x01Test"];
        yield 'invalid email' => ['email', 'invalide'];
        yield 'email with spaces' => ['email', 'a b@example.test'];
        yield 'short password' => ['password', str_repeat('é', 11)];
        yield 'long password' => ['password', str_repeat('é', 129)];
        yield 'array password' => ['password', ['test']];
        yield 'wrong confirmation' => ['password_confirmation', 'une-autre-confirmation'];
        yield 'missing confirmation' => ['password_confirmation', null];
        yield 'refused conditions' => ['terms_accepted', false];
        yield 'numeric consent' => ['terms_accepted', 1];
        yield 'string consent' => ['terms_accepted', 'true'];
        yield 'missing consent' => ['terms_accepted', null];
        yield 'old version' => ['terms_version', 'fixture-v0'];
        yield 'missing version' => ['terms_version', null];
        foreach (['role', 'admin', 'is_admin', 'status', 'author_id', 'email_verified_at', 'is_demo', 'id', 'remember_token', 'name', 'terms_accepted_at', 'profile'] as $field) {
            yield $field.' value' => [$field, 'interdit'];
            yield $field.' null' => [$field, null];
        }
    }

    public function test_missing_fields_and_unknown_query_parameter_are_rejected(): void
    {
        $this->postJson('/register?role=admin', [])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['fields' => ['handle', 'email', 'password', 'password_confirmation', 'terms_accepted', 'terms_version', 'role']]]);
    }

    public function test_absent_terms_configuration_fails_closed_in_json_even_without_accept_header(): void
    {
        config(['registration.terms_version' => null]);
        $this->post('/register', $this->registrationPayload())->assertStatus(503)
            ->assertJsonPath('error.code', 'SERVICE_UNAVAILABLE');
    }

    public function test_registration_is_throttled_before_hashing(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/register', [])->assertUnprocessable();
        }
        $this->postJson('/register', [])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_real_csrf_middleware_rejects_missing_token(): void
    {
        $this->app->bind(RequireCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends RequireCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->postJson('/register', $this->registrationPayload())->assertStatus(419)
            ->assertJsonPath('error.code', 'CSRF_TOKEN_MISMATCH');
    }

    public function test_password_is_redacted_when_the_dto_is_dumped(): void
    {
        $data = new RegisterMemberData('Awa', 'awa@example.test', 'fictif-secret-non-journalise', 'fixture-v1');
        $this->assertStringNotContainsString('fictif-secret-non-journalise', print_r($data, true));
    }
}

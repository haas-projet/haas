<?php

namespace Tests\Integration;

use App\Data\Identity\RegisterMemberData;
use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Exceptions\Identity\RegistrationRejected;
use App\Models\User;
use App\Notifications\Identity\VerifyAccountEmail;
use App\Services\Identity\RegisterMemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger as LaravelLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Tests\PostgresTestCase;
use Tests\Support\RegistrationFixtures;

final class RegistrationTest extends PostgresTestCase
{
    use RefreshDatabase, RegistrationFixtures;

    public function test_registration_creates_an_unverified_member_profile_and_versioned_acceptance(): void
    {
        $this->freezeTime();
        Notification::fake();
        $payload = $this->registrationPayload();
        $payload['email'] = ' AWA@Example.test ';
        $payload['handle'] = ' Awa ';
        $response = $this->postJson('/register', $payload)->assertCreated();
        $user = User::sole();
        $response->assertExactJson(['data' => ['id' => $user->id, 'handle' => 'Awa', 'email_verified' => false]])
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Request-ID');
        $this->assertSame('awa@example.test', $user->email);
        $this->assertSame(Role::Member, $user->role);
        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertFalse($user->is_demo);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->getRawOriginal('remember_token'));
        $this->assertGuest();
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'bio' => '', 'primary_language' => 'fr']);
        $this->assertDatabaseHas('user_terms_acceptances', ['user_id' => $user->id, 'version' => 'fixture-v1', 'accepted_at' => now()]);
        $this->assertTrue(Hash::check($payload['password'], $user->password));
        $this->assertSame('argon2id', Hash::info($user->password)['algoName']);
        Notification::assertSentTo($user, VerifyAccountEmail::class);
        $schema = Yaml::parseFile(base_path('../docs/api/openapi/identity.yaml'))['components']['schemas']['RegisteredMember'];
        $this->assertEqualsCanonicalizing($schema['required'], array_keys($response->json('data')));
    }

    #[DataProvider('passwords')]
    public function test_password_is_fully_hashed_and_verified_without_trimming_or_truncation(string $password): void
    {
        $payload = $this->registrationPayload();
        $payload['password'] = $payload['password_confirmation'] = $password;
        $this->postJson('/register', $payload)->assertCreated();
        $hash = User::sole()->password;
        $this->assertTrue(Hash::check($password, $hash));
        $this->assertFalse(Hash::check(mb_substr($password, 0, -1).'X', $hash));
        if (strlen($password) > 72) {
            $this->assertFalse(Hash::check(substr($password, 0, 72), $hash));
        }
    }

    /** @return iterable<string, array{string}> */
    public static function passwords(): iterable
    {
        yield '128 Unicode characters' => [str_repeat('é', 127).'🌍'];
        yield '12 Unicode characters' => [str_repeat('🌍', 12)];
        yield 'spaces preserved' => ['  '.str_repeat('a', 90).'  '];
        yield 'bcrypt-looking text is still a password' => ['$2y$04$'.str_repeat('a', 53)];
    }

    #[DataProvider('duplicates')]
    public function test_normalized_duplicates_are_rejected_without_partial_writes(string $field, string $value): void
    {
        User::factory()->create(['handle' => 'Awa', 'email' => 'awa@example.test']);
        $payload = $this->registrationPayload();
        $payload['handle'] = 'Nouveau';
        $payload['email'] = 'nouveau@example.test';
        $payload[$field] = $value;
        $response = $this->postJson('/register', $payload)->assertUnprocessable();
        $response->assertJsonPath('error.code', 'VALIDATION_FAILED')->assertJsonStructure(['error' => ['fields' => [$field]]]);
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString('SQL', $body);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('profiles', 0);
        $this->assertDatabaseCount('user_terms_acceptances', 0);
    }

    /** @return iterable<string, array{string, string}> */
    public static function duplicates(): iterable
    {
        yield 'case-insensitive handle' => ['handle', ' aWA '];
        yield 'normalized email' => ['email', ' AWA@Example.test '];
    }

    public function test_service_rechecks_the_current_terms_version(): void
    {
        $data = new RegisterMemberData('Awa', 'awa@example.test', 'mot-de-passe-fictif', 'fixture-v1');
        config(['registration.terms_version' => 'fixture-v2']);
        try {
            app(RegisterMemberService::class)->register($data);
            $this->fail('La version obsolète devait être refusée.');
        } catch (RegistrationRejected $exception) {
            $this->assertArrayHasKey('terms_version', $exception->fields);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_storage_failure_rolls_back_all_writes_and_logs_no_credentials(): void
    {
        $handler = new TestHandler;
        Log::swap(new LaravelLogger(new Logger('registration-test', [$handler])));
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION reject_fixture_terms() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'fixture storage failure' USING ERRCODE = '23514'; END; $$;
            CREATE TRIGGER reject_fixture_terms BEFORE INSERT ON user_terms_acceptances
            FOR EACH ROW EXECUTE FUNCTION reject_fixture_terms();
            SQL);
        $payload = $this->registrationPayload();
        $response = $this->postJson('/register', $payload)->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('profiles', 0);
        $this->assertDatabaseCount('user_terms_acceptances', 0);
        $this->assertNotEmpty($handler->getRecords());
        $record = $handler->getRecords()[0];
        $this->assertStringContainsString('SQLSTATE 23514', $record->message);
        // Vérifier le journal formaté réellement écrit, pas un dump récursif du conteneur de test.
        $observed = $response->getContent().$handler->getFormatter()->format($record);
        foreach ([$payload['email'], $payload['password'], '$argon2id$', 'insert into', 'fixture storage failure'] as $private) {
            $this->assertStringNotContainsString($private, $observed);
        }
    }
}

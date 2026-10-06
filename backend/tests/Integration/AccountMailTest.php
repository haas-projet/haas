<?php

namespace Tests\Integration;

use App\Data\Identity\LoginData;
use App\Data\Identity\ResetPasswordData;
use App\Data\Identity\VerifyEmailData;
use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\User;
use App\Services\Identity\AuthenticateMemberService;
use App\Services\Identity\ResetPasswordService;
use App\Services\Identity\VerifyEmailService;
use App\Support\Http\MemberSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Log\Logger as LaravelLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\AccountMailFixtures;
use Tests\Support\SpaHttpRequests;

final class AccountMailTest extends PostgresTestCase
{
    use AccountMailFixtures, RefreshDatabase, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
        Route::middleware(['api', 'auth:sanctum', 'verified'])->post('/api/v1/publication-fixture', fn () => ['published' => true]);
    }

    public function test_reset_request_has_same_response_for_all_accounts_and_recent_links(): void
    {
        $user = User::factory()->create();
        $suspended = User::factory()->create(['status' => AccountStatus::Suspended]);
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $first = $this->browserRequest('POST', '/forgot-password', ['email' => strtoupper($user->email)])->assertStatus(202);
        foreach ([$user->email, 'absent@example.test', $suspended->email] as $email) {
            $this->assertSame($first->json(), $this->browserRequest('POST', '/forgot-password', ['email' => $email])->assertStatus(202)->json());
        }
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->assertDatabaseCount('jobs', 1);
        $token = $this->passwordToken($user);
        $this->assertTrue(Hash::check($token, DB::table('password_reset_tokens')->sole()->token));
        $payload = DB::table('jobs')->sole()->payload;
        $this->assertStringNotContainsString($token, $payload);
        $this->assertStringNotContainsString($user->email, $payload);
        $body = $first->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString($user->email, $body);
    }

    public function test_reset_is_single_use_preserves_verification_and_revokes_all_sessions(): void
    {
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create();
        $this->loginMember($user);
        $oldCookies = $this->browserCookies;
        DB::table('sessions')->insert(['id' => 'second-session-fixture', 'user_id' => $user->id, 'payload' => 'fixture', 'last_activity' => time()]);
        $this->browserRequest('POST', '/forgot-password', ['email' => $user->email])->assertStatus(202);
        $token = $this->passwordToken($user);
        $password = str_repeat('é', 127).'🌍';
        $this->browserRequest('POST', '/reset-password', $this->resetBody($user, $token, $password))->assertOk();
        $this->assertTrue(Hash::check($password, $user->refresh()->password));
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame(0, DB::table('sessions')->whereNotNull('user_id')->count());
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Event::assertDispatchedTimes(PasswordReset::class, 1);
        $this->browserRequest('POST', '/reset-password', $this->resetBody($user, $token))->assertUnprocessable()->assertJsonValidationErrors('token', 'error.fields');
        $this->browserCookies = $oldCookies;
        $this->browserRequest('GET', '/api/v1/session-fixture')->assertUnauthorized();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => $password])->assertOk();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertUnprocessable();
        $this->assertFalse($this->latestAccountNotification()->shouldSend($user, 'mail'));
    }

    public function test_expired_replaced_and_tampered_reset_tokens_are_rejected_and_legacy_password_can_be_replaced(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['password' => password_hash('legacy-fixture', PASSWORD_BCRYPT, ['cost' => 4])]);
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/forgot-password', ['email' => $user->email])->assertStatus(202);
        $oldToken = $this->passwordToken($user);
        $this->browserRequest('POST', '/reset-password', $this->resetBody($user, str_repeat('0', 64)))->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->browserRequest('POST', '/forgot-password', ['email' => $user->email])->assertStatus(202);
        $newToken = $this->passwordToken($user);
        $this->assertNotSame($oldToken, $newToken);
        $this->browserRequest('POST', '/reset-password', $this->resetBody($user, $oldToken))->assertUnprocessable();
        $this->travel(61)->minutes();
        $this->browserRequest('POST', '/reset-password', $this->resetBody($user, $newToken))->assertUnprocessable();
        $this->browserRequest('POST', '/forgot-password', ['email' => $user->email])->assertStatus(202);
        $this->browserRequest('POST', '/reset-password', $this->resetBody($user, $this->passwordToken($user)))->assertOk();
        $this->assertSame('argon2id', Hash::info($user->refresh()->password)['algoName']);
    }

    public function test_verification_unlocks_verified_routes_once_and_resending_is_throttled(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->create();
        $this->loginMember($user);
        $this->browserRequest('POST', '/api/v1/publication-fixture')->assertForbidden();
        $this->browserRequest('POST', '/email/verification-notification')->assertStatus(202);
        $path = $this->verificationPath($user);
        $this->browserRequest('POST', '/email/verification-notification')->assertStatus(429)->assertHeader('Retry-After');
        $this->browserRequest('GET', $path, headers: ['Referer' => 'https://mail.example.test/inbox'], withoutOrigin: true)->assertOk()->assertJsonPath('data.email_verified', true);
        $verifiedAt = $user->refresh()->email_verified_at;
        $this->browserRequest('POST', '/api/v1/publication-fixture')->assertOk();
        $this->travel(61)->seconds();
        $this->browserRequest('GET', $path)->assertOk();
        $this->assertEquals($verifiedAt, $user->refresh()->email_verified_at);
        Event::assertDispatchedTimes(Verified::class, 1);
        $this->browserRequest('POST', '/email/verification-notification')->assertStatus(202);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertFalse($this->latestAccountNotification()->shouldSend($user, 'mail'));
    }

    public function test_invalid_expired_and_other_users_verification_links_are_forbidden_even_for_admin(): void
    {
        $user = User::factory()->create();
        $this->loginMember($user);
        $this->browserRequest('POST', '/email/verification-notification')->assertStatus(202);
        $path = $this->verificationPath($user);
        $this->browserRequest('GET', $path.'changed', headers: ['Referer' => 'https://mail.example.test/inbox'], withoutOrigin: true)->assertForbidden();
        $this->browserRequest('GET', $path, headers: ['Origin' => 'https://untrusted.example.test'])->assertForbidden();
        $this->browserRequest('GET', $path.'&redirect=https://untrusted.test')->assertForbidden();
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->loginMember($admin);
        $this->browserRequest('GET', $path)->assertForbidden();
        $this->assertFalse($user->refresh()->hasVerifiedEmail());
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->loginMember($user);
        $this->travel(61)->minutes();
        $this->browserRequest('GET', $path)->assertForbidden();
        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_verification_service_rechecks_current_email_and_status_under_lock(): void
    {
        $actor = User::factory()->create();
        $data = new VerifyEmailData($actor->id, sha1($actor->email));
        DB::table('users')->where('id', $actor->id)->update(['email' => 'changed@example.test']);
        try {
            app(VerifyEmailService::class)->verify($actor, $data);
            $this->fail('Le courriel courant devait être recontrôlé.');
        } catch (AuthorizationException) {
            $this->assertFalse($actor->refresh()->hasVerifiedEmail());
        }
        DB::table('users')->where('id', $actor->id)->update(['status' => 'suspended']);
        $this->expectException(AuthorizationException::class);
        app(VerifyEmailService::class)->verify($actor, new VerifyEmailData($actor->id, sha1($actor->email)));
    }

    #[DataProvider('protectedPaths')]
    public function test_reset_rejects_a_login_session_written_after_its_password_was_replaced(string $method, string $path): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        Route::middleware('web')->post('/late-login-fixture', function (Request $request) use ($user, $token) {
            $staleUser = app(AuthenticateMemberService::class)->authenticate(new LoginData($user->email, 'mot-de-passe-de-test'));
            app(ResetPasswordService::class)->reset(new ResetPasswordData($user->email, $token, 'nouveau-mot-de-passe-fictif'));
            app(MemberSession::class)->start($request, $staleUser);

            return response()->json(['fixture' => true]);
        });
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/late-login-fixture')->assertOk();
        $this->browserRequest($method, $path)->assertUnauthorized();
    }

    /** @return iterable<array{string, string}> */
    public static function protectedPaths(): iterable
    {
        yield ['GET', '/api/v1/session-fixture'];
        yield ['POST', '/email/verification-notification'];
    }

    public function test_reset_storage_failure_rolls_back_the_password_token_and_sessions_without_secret_logs(): void
    {
        $user = User::factory()->create();
        $previousHash = $user->password;
        $token = Password::createToken($user);
        DB::table('sessions')->insert(['id' => 'reset-rollback-fixture', 'user_id' => $user->id, 'payload' => 'fixture', 'last_activity' => time()]);
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION reject_fixture_session_delete() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'fixture reset failure' USING ERRCODE = '23514'; END; $$;
            CREATE TRIGGER reject_fixture_session_delete BEFORE DELETE ON sessions
            FOR EACH ROW EXECUTE FUNCTION reject_fixture_session_delete();
            SQL);
        $handler = new TestHandler;
        Log::swap(new LaravelLogger(new Logger('reset-test', [$handler])));
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('POST', '/reset-password', $this->resetBody($user, $token))->assertStatus(500);
        $this->assertSame($previousHash, $user->refresh()->password);
        $this->assertTrue(Password::tokenExists($user, $token));
        $this->assertDatabaseHas('sessions', ['id' => 'reset-rollback-fixture']);
        $this->assertNotEmpty($handler->getRecords());
        $record = $handler->getRecords()[0];
        $observed = $response->getContent().$handler->getFormatter()->format($record);
        foreach ([$user->email, $token, 'nouveau-mot-de-passe-fictif', $previousHash, 'fixture reset failure', 'delete from'] as $secret) {
            $this->assertStringNotContainsString($secret, $observed);
        }
    }
}

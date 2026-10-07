<?php

namespace Tests\Integration;

use App\Enums\Identity\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class MemberSessionTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_login_rotates_session_then_logout_revokes_old_cookies_and_csrf(): void
    {
        $user = User::factory()->create();
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $anonymousCookies = $this->browserCookies;
        $anonymousId = DB::table('sessions')->sole()->id;
        $this->browserRequest('POST', '/login', ['email' => strtoupper($user->email), 'password' => 'mot-de-passe-de-test'])
            ->assertOk()->assertExactJson(['data' => ['id' => $user->id, 'handle' => $user->handle, 'email_verified' => false]]);
        $authenticatedCookies = $this->browserCookies;
        $sessionId = DB::table('sessions')->where('user_id', $user->id)->sole()->id;
        $this->assertNotSame($anonymousId, $sessionId);
        $this->assertDatabaseMissing('sessions', ['id' => $anonymousId]);
        $this->assertNotSame($anonymousCookies['XSRF-TOKEN'], $authenticatedCookies['XSRF-TOKEN']);
        $this->browserRequest('GET', '/api/v1/session-fixture')->assertOk()->assertJsonPath('id', $user->id);
        $this->browserRequest('POST', '/api/v1/session-fixture')->assertOk();
        $this->browserRequest('POST', '/logout')->assertNoContent()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->browserRequest('GET', '/api/v1/session-fixture')->assertUnauthorized();
        $this->browserCookies = $authenticatedCookies;
        $this->browserRequest('GET', '/api/v1/session-fixture')->assertUnauthorized();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'], ['X-XSRF-TOKEN' => $authenticatedCookies['XSRF-TOKEN']])
            ->assertStatus(419);
    }

    public function test_wrong_password_missing_account_and_legacy_bcrypt_have_same_public_error(): void
    {
        $user = User::factory()->create();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $first = $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'incorrect-fictitious-password'])->assertUnprocessable();
        $second = $this->browserRequest('POST', '/login', ['email' => 'absent@example.test', 'password' => 'mot-de-passe-de-test'])->assertUnprocessable();
        DB::table('users')->where('id', $user->id)->update(['password' => password_hash('mot-de-passe-de-test', PASSWORD_BCRYPT, ['cost' => 4])]);
        $third = $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertUnprocessable();
        $this->assertSame($first->json('error'), $second->json('error'));
        $this->assertSame($first->json('error'), $third->json('error'));
        $this->assertSame(0, DB::table('sessions')->whereNotNull('user_id')->count());
    }

    public function test_long_unicode_password_and_rehash_are_verified_in_real_login(): void
    {
        $password = str_repeat('é', 127).'🌍';
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['password' => password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 8192, 'time_cost' => 1, 'threads' => 1])]);
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => $password])->assertOk();
        $this->assertFalse(Hash::needsRehash($user->refresh()->password));
        $this->assertTrue(Hash::check($password, $user->password));
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => str_repeat('é', 127).'X'])->assertUnprocessable();
    }

    public function test_suspended_account_cannot_log_in_and_existing_session_is_invalidated(): void
    {
        $user = User::factory()->create();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
        $user->status = AccountStatus::Suspended;
        $user->save();
        $this->browserRequest('GET', '/api/v1/session-fixture')->assertForbidden()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com');
        $this->assertSame(0, DB::table('sessions')->whereNotNull('user_id')->count());
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertForbidden();
    }

    public function test_forged_logout_does_not_end_the_authenticated_session(): void
    {
        $user = User::factory()->create();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
        $this->browserRequest('POST', '/logout', sendXsrf: false)->assertStatus(419);
        $this->browserRequest('GET', '/api/v1/session-fixture')->assertOk()->assertJsonPath('id', $user->id);
        $this->browserRequest('POST', '/logout', headers: ['Origin' => 'https://demo.example.com'])->assertForbidden();
        $this->browserRequest('GET', '/api/v1/session-fixture')->assertOk();
    }

    public function test_changed_password_and_session_expiration_reject_previous_cookies(): void
    {
        $user = User::factory()->create();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
        $this->browserRequest('GET', '/api/v1/session-fixture', headers: ['Origin' => 'https://api.haas.example.com'])->assertOk();
        $user->password = 'nouveau-mot-de-passe-fictif';
        $user->save();
        $this->browserRequest('GET', '/api/v1/session-fixture')->assertUnauthorized();
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'nouveau-mot-de-passe-fictif'])->assertOk();
        $this->travel(121)->minutes();
        // Un appel forgé transmet le cookie périmé : le serveur doit toujours le refuser.
        $this->browserRequest('GET', '/api/v1/session-fixture', sendExpiredCookies: true)->assertUnauthorized();
    }

    public function test_invalid_origin_or_csrf_cannot_use_an_authenticated_session(): void
    {
        $user = User::factory()->create();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
        $this->browserRequest('GET', '/api/v1/session-fixture', headers: ['Origin' => ''])->assertForbidden();
        $this->browserRequest('POST', '/api/v1/session-fixture', headers: ['X-XSRF-TOKEN' => 'invalid-token'])->assertStatus(419);
        $this->browserRequest('GET', '/api/v1/session-fixture')->assertOk();
    }
}

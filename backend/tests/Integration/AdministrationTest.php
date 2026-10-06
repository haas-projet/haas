<?php

namespace Tests\Integration;

use App\Data\Identity\ManageAccountData;
use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Exceptions\Identity\AccountVersionConflict;
use App\Exceptions\Identity\AuthenticationStorageFailed;
use App\Models\User;
use App\Services\Identity\ManageAccountService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class AdministrationTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    private const string REASON = 'Décision motivée avec données fictives uniquement.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_suspension_revokes_all_sessions_and_a_late_session_cannot_resurrect_after_reactivation(): void
    {
        $admin = User::factory()->verified()->create(['role' => Role::Admin]);
        $member = User::factory()->verified()->create();
        $this->login($member);
        $oldCookies = $this->browserCookies;
        $lateSession = (array) DB::table('sessions')->where('user_id', $member->id)->sole();
        $this->login($member);
        $this->assertSame(2, DB::table('sessions')->where('user_id', $member->id)->count());
        $this->login($admin);
        $path = '/api/v1/admin/members/'.$member->id;
        $this->browserRequest('PATCH', $path, ['lock_version' => 0, 'status' => 'suspended', 'reason' => self::REASON], sendXsrf: false)->assertStatus(419);
        $this->browserRequest('PATCH', $path, ['lock_version' => 0, 'status' => 'suspended', 'reason' => self::REASON])
            ->assertOk()->assertJsonPath('data.status', 'suspended')->assertJsonPath('data.lock_version', 1);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $member->id)->count());
        $adminCookies = $this->browserCookies;
        $this->browserCookies = $oldCookies;
        $this->browserRequest('GET', '/api/v1/me')->assertUnauthorized();
        $this->browserRequest('GET', '/api/v1/account-access')->assertOk();
        $this->browserCookies = $adminCookies;
        $this->browserRequest('PATCH', $path, ['lock_version' => 1, 'status' => 'active', 'reason' => self::REASON])->assertOk();
        DB::table('sessions')->updateOrInsert(['id' => $lateSession['id']], $lateSession);
        $this->browserCookies = $oldCookies;
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 0, 'bio' => 'Refusé'])->assertUnauthorized();
        $this->assertDatabaseCount('profiles', 0);
        $this->assertDatabaseCount('account_decisions', 2);
        $this->assertDatabaseCount('content_revisions', 2);
        $decision = DB::table('account_decisions')->orderBy('security_version')->first();
        $this->assertNotNull($decision);
        $this->assertSame(self::REASON, Crypt::decryptString($decision->reason_encrypted));
        $this->assertStringNotContainsString(self::REASON, json_encode(DB::table('content_revisions')->get(), JSON_THROW_ON_ERROR));
    }

    public function test_only_current_verified_admin_can_list_or_change_accounts(): void
    {
        $target = User::factory()->verified()->create();
        $this->browserRequest('GET', '/api/v1/admin/members')->assertUnauthorized();
        foreach ([Role::Member, Role::Moderator] as $role) {
            $actor = User::factory()->verified()->create(['role' => $role]);
            $this->login($actor);
            $this->browserRequest('GET', '/api/v1/admin/members')->assertForbidden();
            $this->browserRequest('PATCH', '/api/v1/admin/members/'.$target->id, ['lock_version' => 0, 'role' => 'admin', 'reason' => self::REASON])->assertForbidden();
        }
        $admin = User::factory()->verified()->create(['role' => Role::Admin]);
        $this->login($admin);
        $list = $this->browserRequest('GET', '/api/v1/admin/members?per_page=1')->assertOk()->assertJsonPath('meta.per_page', 1);
        $this->assertEqualsCanonicalizing(['id', 'handle', 'role', 'status', 'lock_version'], array_keys($list->json('data.0')));
        User::whereKey($admin->id)->update(['role' => Role::Member]);
        $this->expectException(AuthorizationException::class);
        app(ManageAccountService::class)->update($admin, $target->id, new ManageAccountData(0, 'role', 'moderator', self::REASON));
    }

    public function test_role_change_revokes_sessions_and_conflicts_do_not_duplicate_audit(): void
    {
        $admin = User::factory()->verified()->create(['role' => Role::Admin]);
        $target = User::factory()->verified()->create();
        $this->login($target);
        $cookies = $this->browserCookies;
        $this->login($admin);
        $path = '/api/v1/admin/members/'.$target->id;
        $body = ['lock_version' => 0, 'role' => 'moderator', 'reason' => self::REASON];
        $this->browserRequest('PATCH', $path, $body + ['email' => 'forged@example.test'])->assertUnprocessable();
        $this->browserRequest('PATCH', $path, $body + ['status' => 'suspended'])->assertUnprocessable();
        $this->browserRequest('PATCH', $path, $body)->assertOk()->assertJsonPath('data.role', 'moderator');
        $this->browserRequest('PATCH', $path, $body)->assertConflict();
        $this->assertDatabaseCount('account_decisions', 1);
        $this->browserCookies = $cookies;
        $this->browserRequest('GET', '/api/v1/me')->assertUnauthorized();
        $this->login($target);
        $this->browserRequest('GET', '/api/v1/me')->assertOk()->assertJsonPath('data.role', 'moderator');
    }

    public function test_last_active_verified_admin_is_preserved(): void
    {
        $admin = User::factory()->verified()->create(['role' => Role::Admin]);
        User::factory()->create(['role' => Role::Admin]);
        User::factory()->verified()->create(['role' => Role::Admin, 'status' => AccountStatus::Suspended]);
        foreach ([['role', 'member'], ['status', 'suspended']] as [$field, $value]) {
            try {
                app(ManageAccountService::class)->update($admin, $admin->id, new ManageAccountData(0, $field, $value, self::REASON));
                $this->fail('Le dernier administrateur actif vérifié doit être préservé.');
            } catch (AccountVersionConflict) {
                $this->assertSame(Role::Admin, $admin->refresh()->role);
                $this->assertSame(AccountStatus::Active, $admin->status);
            }
        }
        $this->assertDatabaseCount('account_decisions', 0);
    }

    public function test_audit_failure_rolls_back_account_sessions_and_decision(): void
    {
        $admin = User::factory()->verified()->create(['role' => Role::Admin]);
        $target = User::factory()->verified()->create();
        $this->login($target);
        DB::statement('ALTER TABLE content_revisions ADD CONSTRAINT b32_failure CHECK (false)');
        try {
            app(ManageAccountService::class)->update($admin, $target->id, new ManageAccountData(0, 'status', 'suspended', self::REASON));
            $this->fail('Échec SQL attendu.');
        } catch (AuthenticationStorageFailed $exception) {
            $this->assertNull($exception->getPrevious());
            $this->assertSame(AccountStatus::Active, $target->refresh()->status);
            $this->assertSame(0, $target->security_version);
            $this->assertDatabaseCount('account_decisions', 0);
            $this->assertSame(1, DB::table('sessions')->where('user_id', $target->id)->count());
        } finally {
            DB::statement('ALTER TABLE content_revisions DROP CONSTRAINT b32_failure');
        }
    }

    private function login(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}

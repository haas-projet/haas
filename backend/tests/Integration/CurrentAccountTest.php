<?php

namespace Tests\Integration;

use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\User;
use App\Queries\Identity\CurrentAccountQuery;
use App\Support\Identity\MemberAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Yaml\Yaml;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class CurrentAccountTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    #[DataProvider('roles')]
    public function test_real_session_returns_only_current_account_and_current_capabilities(Role $role, bool $verified): void
    {
        $user = User::factory()->create(['role' => $role, 'email_verified_at' => $verified ? now() : null]);
        $this->login($user);
        $response = $this->browserRequest('GET', '/api/v1/me')->assertOk()->assertExactJson(['data' => [
            'id' => $user->id, 'handle' => $user->handle, 'email' => $user->email, 'role' => $role->value,
            'status' => 'active', 'email_verified' => $verified, 'is_demo' => false,
            'can' => ['manage_account_mail' => true, 'update_profile' => $verified, 'participate' => $verified,
                'moderate' => $verified && $role !== Role::Member, 'administer' => $verified && $role === Role::Admin],
        ]])->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com')->assertHeader('Access-Control-Allow-Credentials', 'true');
        $schema = Yaml::parseFile(base_path('../docs/api/openapi/current-account.yaml'))['components']['schemas']['Me'];
        $this->assertEqualsCanonicalizing($schema['required'], array_keys($response->json('data')));
        $this->assertEqualsCanonicalizing($schema['properties']['can']['required'], array_keys($response->json('data.can')));
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->browserRequest('GET', '/api/v1/me')->assertUnauthorized();
    }

    /** @return iterable<string, array{Role, bool}> */
    public static function roles(): iterable
    {
        foreach (Role::cases() as $role) {
            yield $role->value.' unverified' => [$role, false];
            yield $role->value.' verified' => [$role, true];
        }
    }

    public function test_forged_fields_cannot_select_another_account_or_change_roles(): void
    {
        $user = User::factory()->verified()->create();
        $other = User::factory()->admin()->create();
        $this->login($user);
        foreach (['user_id' => $other->id, 'role' => 'admin', 'author_id' => $other->id, 'status' => 'active', 'include' => 'password', 'can' => null] as $field => $value) {
            $this->browserRequest('GET', '/api/v1/me', [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
        }
        $this->browserRequest('GET', '/api/v1/me?user_id='.$other->id)->assertUnprocessable();
        $this->browserRequest('GET', '/api/v1/me/'.$other->id)->assertNotFound();
        $this->browserRequest('POST', '/api/v1/me', ['role' => 'admin'])->assertStatus(405);
        $this->browserRequest('GET', '/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonPath('data.role', 'member');
        $this->assertSame(Role::Member, $user->refresh()->role);
    }

    public function test_changed_permissions_are_read_from_sql_and_never_from_session_cache(): void
    {
        $user = User::factory()->verified()->admin()->demo()->create();
        $this->login($user);
        $this->browserRequest('GET', '/api/v1/me')->assertOk()->assertJsonPath('data.can.administer', true)->assertJsonPath('data.is_demo', true);
        DB::table('users')->where('id', $user->id)->update(['role' => 'member', 'email_verified_at' => null]);
        $this->browserRequest('GET', '/api/v1/me')->assertOk()->assertJsonPath('data.can.administer', false)
            ->assertJsonPath('data.can.participate', false)->assertJsonPath('data.email_verified', false);
        $current = app(CurrentAccountQuery::class)->get($user);
        $this->assertSame(Role::Member, $current->role);
        $this->assertArrayNotHasKey('password', $current->getAttributes());
        $this->assertArrayNotHasKey('remember_token', $current->getAttributes());
    }

    public function test_suspension_is_identified_after_authentication_and_recourse_survives_revocation(): void
    {
        $user = User::factory()->verified()->admin()->create();
        $this->login($user);
        $cookies = $this->browserCookies;
        $user->status = AccountStatus::Suspended;
        $user->save();
        $this->browserRequest('GET', '/api/v1/me')->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED')
            ->assertJsonMissingPath('data')->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com');
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->browserCookies = $cookies;
        $this->browserRequest('GET', '/api/v1/me')->assertUnauthorized();
        $this->browserRequest('GET', '/api/v1/account-access')->assertOk();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'incorrect-fictitious-password'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])
            ->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
    }

    public function test_information_can_be_the_first_request_after_suspension(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $user->status = AccountStatus::Suspended;
        $user->save();
        config(['account.support_email' => 'recours@example.test']);
        $this->browserRequest('GET', '/api/v1/account-access')->assertOk()->assertJsonPath('data.contact_email', 'recours@example.test');
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->browserRequest('GET', '/api/v1/me')->assertUnauthorized();
    }

    public function test_private_account_is_not_exposed_to_untrusted_origin(): void
    {
        $user = User::factory()->verified()->create();
        $this->login($user);
        foreach (['https://demo.example.com', 'https://preview.vercel.app', 'null'] as $origin) {
            $this->browserRequest('GET', '/api/v1/me', headers: ['Origin' => $origin])->assertForbidden()->assertJsonMissingPath('data');
        }
        $this->browserRequest('GET', '/api/v1/me')->assertOk();
    }

    public function test_temporary_failure_does_not_destroy_the_existing_session(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $this->app->bind(CurrentAccountQuery::class, fn () => throw new ServiceUnavailableHttpException);
        $this->browserRequest('GET', '/api/v1/me')->assertStatus(503)->assertJsonMissingPath('data');
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->app->offsetUnset(CurrentAccountQuery::class);
        $this->browserRequest('GET', '/api/v1/me')->assertOk();
    }

    public function test_verified_ownership_rule_is_enforced_over_http_without_admin_bypass(): void
    {
        // Fixture de droit seulement : le modèle et la résolution métier appartiennent à B11/B19.
        $owner = User::factory()->verified()->create();
        $administrator = User::factory()->verified()->admin()->create();
        Event::fake(['fixture-resolution']);
        Gate::define('resolve-fixture', fn (User $actor): bool => app(MemberAccess::class)->owns($actor, $owner->id));
        Route::middleware(['api', 'auth:sanctum', 'verified'])->post('/api/v1/resolve-fixture', function (): array {
            Gate::authorize('resolve-fixture');
            Event::dispatch('fixture-resolution');

            return ['resolved' => true];
        });
        $this->login($administrator);
        $this->browserRequest('POST', '/api/v1/resolve-fixture', ['author_id' => $administrator->id])->assertForbidden();
        Event::assertNotDispatched('fixture-resolution');
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->login($owner);
        $this->browserRequest('POST', '/api/v1/resolve-fixture')->assertOk();
        Event::assertDispatchedTimes('fixture-resolution', 1);
        DB::table('users')->where('id', $owner->id)->update(['email_verified_at' => null]);
        $this->browserRequest('POST', '/api/v1/resolve-fixture')->assertForbidden();
        Event::assertDispatchedTimes('fixture-resolution', 1);
    }

    private function login(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}

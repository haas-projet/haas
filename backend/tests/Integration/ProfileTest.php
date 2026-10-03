<?php

namespace Tests\Integration;

use App\Data\Identity\UpdateProfileData;
use App\Enums\Identity\AccountStatus;
use App\Exceptions\Identity\ProfileStorageFailed;
use App\Models\Profile;
use App\Models\Technology;
use App\Models\User;
use App\Services\Identity\UpdateProfileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class ProfileTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_public_profile_exposes_only_voluntary_fields_and_demo_label(): void
    {
        $user = User::factory()->verified()->admin()->demo()->create(['handle' => 'Élodie_Diop']);
        Profile::factory()->for($user)->create(['bio' => 'Biographie publique.', 'country' => 'Sénégal', 'primary_language' => 'fr', 'github_url' => 'https://github.com/haas-projet']);
        $tech = Technology::factory()->create(['slug' => 'php', 'name' => 'PHP']);
        $user->technologies()->attach($tech);
        $response = $this->browserRequest('GET', '/api/v1/members/'.rawurlencode($user->handle))->assertOk()
            ->assertExactJson(['data' => ['id' => $user->id, 'handle' => $user->handle, 'avatar_initials' => 'ÉD', 'bio' => 'Biographie publique.',
                'country' => 'Sénégal', 'primary_language' => 'fr', 'github_url' => 'https://github.com/haas-projet',
                'technologies' => [['id' => $tech->id, 'slug' => 'php', 'name' => 'PHP']], 'is_demo' => true, 'contributions' => null]])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringNotContainsString($user->email, (string) $response->getContent());
        $contract = Yaml::parseFile(base_path('../docs/api/openapi/profiles.yaml'));
        $this->assertEqualsCanonicalizing($contract['components']['schemas']['ProfileFields']['required'], array_keys($response->json('data')));
        $this->browserRequest('GET', '/api/v1/members/'.rawurlencode($user->handle).'?include=email')->assertUnprocessable();
        $this->browserRequest('GET', '/api/v1/me/profile')->assertUnauthorized();
    }

    public function test_unverified_suspended_and_missing_public_profiles_have_same_safe_404(): void
    {
        $unverified = User::factory()->create();
        $suspended = User::factory()->verified()->suspended()->create();
        foreach ([$unverified->handle, $suspended->handle, 'absent'] as $handle) {
            $this->browserRequest('GET', '/api/v1/members/'.$handle)->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        }
        $administrator = User::factory()->verified()->admin()->create();
        $this->login($administrator);
        $this->browserRequest('GET', '/api/v1/members/'.$suspended->handle)->assertNotFound();
    }

    public function test_verified_owner_can_update_profile_and_eight_technologies_atomically(): void
    {
        $user = User::factory()->verified()->create();
        $ids = Technology::factory()->count(8)->create()->modelKeys();
        $this->login($user);
        $this->browserRequest('GET', '/api/v1/me/profile')->assertOk()->assertJsonPath('data.lock_version', 0)->assertJsonPath('data.can_update', true);
        $this->assertDatabaseCount('profiles', 0);
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 0, 'bio' => str_repeat('é', 500), 'country' => 'Sénégal',
            'primary_language' => 'fr', 'github_url' => 'https://github.com/haas-projet', 'technology_ids' => $ids])
            ->assertOk()->assertJsonPath('data.lock_version', 1)->assertJsonCount(8, 'data.technologies');
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'bio' => str_repeat('é', 500), 'lock_version' => 1]);
        $this->assertSame(8, $user->technologies()->count());
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 1, 'country' => null])->assertOk()->assertJsonPath('data.lock_version', 2)
            ->assertJsonPath('data.bio', str_repeat('é', 500))->assertJsonCount(8, 'data.technologies');
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 2, 'bio' => '', 'github_url' => null, 'technology_ids' => []])
            ->assertOk()->assertJsonPath('data.bio', '')->assertJsonCount(0, 'data.technologies');
        $this->browserRequest('GET', '/api/v1/members/'.$user->handle)->assertOk()->assertJsonPath('data.bio', '')->assertJsonMissingPath('data.lock_version');
    }

    public function test_stale_version_and_missing_technology_leave_all_existing_values_untouched(): void
    {
        $user = User::factory()->verified()->create();
        Profile::factory()->for($user)->create(['bio' => 'Original']);
        $tech = Technology::factory()->create();
        $user->technologies()->attach($tech);
        $this->login($user);
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 0, 'bio' => 'Ne pas écrire', 'technology_ids' => ['b0fbc5aa-662e-4cac-83eb-a21259c98e03']])
            ->assertUnprocessable()->assertJsonValidationErrors('technology_ids', 'error.fields');
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 4, 'bio' => 'Ne pas écrire', 'technology_ids' => []])
            ->assertStatus(409)->assertJsonPath('error.code', 'RESOURCE_CONFLICT');
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'bio' => 'Original', 'lock_version' => 0]);
        $this->assertSame([$tech->id], $user->technologies()->pluck('technologies.id')->all());
    }

    public function test_no_visitor_unverified_or_admin_can_edit_a_foreign_profile(): void
    {
        $owner = User::factory()->verified()->create();
        Profile::factory()->for($owner)->create(['bio' => 'Original']);
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 0, 'bio' => 'Interdit'])->assertUnauthorized();
        $unverified = User::factory()->create();
        $this->login($unverified);
        $this->browserRequest('GET', '/api/v1/me/profile')->assertOk()->assertJsonPath('data.can_update', false);
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 0, 'bio' => 'Interdit'])->assertForbidden();
        $this->browserRequest('POST', '/logout');
        $admin = User::factory()->verified()->admin()->create();
        $this->login($admin);
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 0, 'bio' => 'Interdit', 'user_id' => $owner->id])->assertUnprocessable();
        $this->browserRequest('PATCH', '/api/v1/members/'.$owner->handle, ['lock_version' => 0, 'bio' => 'Interdit'])->assertStatus(405);
        $this->assertDatabaseHas('profiles', ['user_id' => $owner->id, 'bio' => 'Original', 'lock_version' => 0]);
    }

    public function test_patch_requires_csrf_and_trusted_origin_and_preflight_allows_only_declared_methods(): void
    {
        $user = User::factory()->verified()->create();
        $this->login($user);
        $body = ['lock_version' => 0, 'bio' => 'Texte'];
        $this->browserRequest('PATCH', '/api/v1/me/profile', $body, sendXsrf: false)->assertStatus(419);
        $this->browserRequest('PATCH', '/api/v1/me/profile', $body, headers: ['Origin' => 'https://preview.vercel.app'])->assertForbidden();
        $this->browserRequest('OPTIONS', '/api/v1/me/profile', headers: ['Access-Control-Request-Method' => 'PATCH'])
            ->assertNoContent()->assertHeader('Access-Control-Allow-Methods', 'GET, HEAD, POST, PATCH, OPTIONS');
        $this->assertDatabaseCount('profiles', 0);
    }

    public function test_service_rechecks_suspension_and_verification_from_locked_user(): void
    {
        $user = User::factory()->verified()->create();
        foreach ([['status' => 'suspended'], ['status' => 'active', 'email_verified_at' => null]] as $change) {
            DB::table('users')->where('id', $user->id)->update($change);
            try {
                app(UpdateProfileService::class)->update($user, new UpdateProfileData(0, ['bio' => 'Interdit'], null));
                $this->fail('Un acteur devenu inéligible ne doit pas écrire.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('profiles', 0);
            }
        }
    }

    public function test_sql_failure_rolls_back_bio_version_and_pivots_without_leaking_bindings(): void
    {
        $user = User::factory()->verified()->create();
        Profile::factory()->for($user)->create(['bio' => 'Original']);
        $tech = Technology::factory()->create();
        DB::statement('ALTER TABLE user_technologies ADD CONSTRAINT fixture_reject_profile_pivot CHECK (false)');
        try {
            app(UpdateProfileService::class)->update($user, new UpdateProfileData(0, ['bio' => 'Texte privé de fixture'], [$tech->id]));
            $this->fail('La panne SQL doit être remontée.');
        } catch (ProfileStorageFailed $exception) {
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('Texte privé', $exception->getMessage());
            $this->assertStringNotContainsString($tech->id, $exception->getMessage());
        }
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'bio' => 'Original', 'lock_version' => 0]);
        $this->assertDatabaseCount('user_technologies', 0);
    }

    public function test_public_profile_disappears_on_suspension_without_stale_cache(): void
    {
        $user = User::factory()->verified()->create();
        $this->browserRequest('GET', '/api/v1/members/'.$user->handle)->assertOk();
        $user->status = AccountStatus::Suspended;
        $user->save();
        $this->browserRequest('GET', '/api/v1/members/'.$user->handle)->assertNotFound();
    }

    public function test_legacy_unsafe_link_is_never_exposed_or_silently_rewritten(): void
    {
        $user = User::factory()->verified()->create();
        Profile::factory()->for($user)->create(['github_url' => 'javascript:alert(1)']);
        $this->browserRequest('GET', '/api/v1/members/'.$user->handle)->assertOk()->assertJsonPath('data.github_url', null);
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'github_url' => 'javascript:alert(1)']);
    }

    public function test_technology_catalogue_is_public_bounded_and_stably_ordered(): void
    {
        $this->browserRequest('GET', '/api/v1/technologies')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        foreach (['typescript', 'laravel', 'php'] as $slug) {
            Technology::factory()->create(['slug' => $slug, 'name' => ucfirst($slug)]);
        }
        $this->browserRequest('GET', '/api/v1/technologies?per_page=2')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'laravel')->assertJsonPath('data.1.slug', 'php')->assertJsonPath('meta.total', 3);
        $this->browserRequest('GET', '/api/v1/technologies?per_page=2&page=2')->assertOk()->assertJsonPath('data.0.slug', 'typescript');
        $this->browserRequest('GET', '/api/v1/technologies?per_page=51')->assertUnprocessable();
        $this->browserRequest('GET', '/api/v1/technologies?sort=users')->assertUnprocessable();
    }

    private function login(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}

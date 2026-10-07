<?php

namespace Tests\Integration;

use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Data\Identity\UpdateProfileData;
use App\Enums\Identity\AccountStatus;
use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Exceptions\Identity\ProfileVersionConflict;
use App\Models\Profile;
use App\Models\Technology;
use App\Models\User;
use App\Services\Idempotency\IdempotencyService;
use App\Services\Identity\UpdateProfileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use RuntimeException;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class IdempotencyTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    private const string KEY = 'fda9a000-3333-4444-8888-123456789abc';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_http_retry_returns_same_profile_with_one_version_and_audit_and_no_private_snapshot(): void
    {
        $this->freezeTime();
        // Forcer la collecte pour vérifier la reconnexion après expiration réelle des cookies.
        config(['session.lottery' => [100, 100]]);
        $owner = User::factory()->verified()->create();
        $ids = Technology::factory()->count(2)->create()->modelKeys();
        $this->login($owner);
        $body = ['lock_version' => 0, 'bio' => 'Texte privé fictif', 'technology_ids' => $ids];
        $first = $this->browserRequest('PATCH', '/api/v1/me/profile', $body, ['Idempotency-Key' => self::KEY])->assertOk()->assertJsonPath('data.lock_version', 1);
        $replay = ['technology_ids' => array_map('strtoupper', array_reverse($ids)), 'bio' => 'Texte privé fictif', 'lock_version' => 0];
        $this->browserRequest('PATCH', '/api/v1/me/profile', $replay, ['Idempotency-Key' => strtoupper(self::KEY)])
            ->assertOk()->assertExactJson($first->json())->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('content_revisions', 1);
        $row = DB::table('api_idempotency')->sole();
        $this->assertSame('PATCH /api/v1/members/'.$owner->id.'/profile', $row->route_target);
        $this->assertSame(hash('sha256', self::KEY), $row->key_hash);
        $this->assertSame(86400, strtotime($row->expires_at) - strtotime($row->created_at));
        $this->assertEquals(['references' => ['profile_id' => $owner->id], 'version' => 1], json_decode($row->response, true, flags: JSON_THROW_ON_ERROR));
        $stored = json_encode($row, JSON_THROW_ON_ERROR);
        foreach ([self::KEY, 'Texte privé fictif', $owner->email, $owner->password] as $private) {
            $this->assertStringNotContainsString($private, $stored);
        }
        $this->travel(23)->hours();
        $this->login($owner);
        $this->browserRequest('PATCH', '/api/v1/me/profile', $body, ['Idempotency-Key' => self::KEY])->assertOk();
        $this->assertSame($row->expires_at, DB::table('api_idempotency')->sole()->expires_at);
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 0, 'bio' => 'Autre intention'], ['Idempotency-Key' => self::KEY])
            ->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertDatabaseHas('profiles', ['user_id' => $owner->id, 'bio' => 'Texte privé fictif', 'lock_version' => 1]);
        $this->assertDatabaseCount('api_idempotency', 1);
    }

    public function test_bad_header_or_csrf_never_reserves_an_intention(): void
    {
        $owner = User::factory()->verified()->create();
        $this->login($owner);
        foreach (['', 'bad-key', self::KEY.','.self::KEY] as $key) {
            $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 0, 'bio' => 'Test'], ['Idempotency-Key' => $key])
                ->assertUnprocessable()->assertJsonValidationErrors('Idempotency-Key', 'error.fields');
        }
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 0, 'bio' => 'Test'], ['Idempotency-Key' => self::KEY], sendXsrf: false)->assertStatus(419);
        $this->assertDatabaseCount('api_idempotency', 0);
        $this->assertDatabaseCount('content_revisions', 0);
    }

    public function test_same_key_is_isolated_by_user_and_explicit_target(): void
    {
        $first = User::factory()->verified()->create();
        $second = User::factory()->verified()->create();
        $a = $this->update($first, 0, 'Premier');
        $b = $this->update($second, 0, 'Second');
        $this->assertSame($first->id, $a->id);
        $this->assertSame($second->id, $b->id);
        $this->assertSame('Premier', $this->update($first, 0, 'Premier')->profile?->bio);
        $this->assertDatabaseCount('api_idempotency', 2);
        $service = app(IdempotencyService::class);
        foreach (['a', 'b'] as $target) {
            $id = (string) Str::uuid();
            $result = $service->execute($first, new IdempotencyData('POST /api/v1/fixture/'.$target, new IdempotencyKey(self::KEY), []),
                function (User $actor) use ($first): void {
                    $this->assertSame($first->id, $actor->id);
                },
                fn (User $actor): StoredCommandResult => new StoredCommandResult(201, ['fixture_id' => $id]),
                fn (User $actor, StoredCommandResult $stored): string => $stored->references['fixture_id']);
            $this->assertSame($id, $result);
        }
        $this->assertDatabaseCount('api_idempotency', 4);
    }

    public function test_expired_intention_is_replaced_only_with_a_valid_new_command(): void
    {
        $this->freezeTime();
        $owner = User::factory()->verified()->create();
        $this->update($owner, 0, 'Initial');
        $oldId = DB::table('api_idempotency')->sole()->id;
        $this->travel(24)->hours();
        $this->assertSame(2, $this->update($owner, 1, 'Après expiration')->profile?->lock_version);
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->assertNotSame($oldId, DB::table('api_idempotency')->sole()->id);
        $this->assertDatabaseCount('content_revisions', 2);
    }

    public function test_current_permissions_are_checked_before_replay_even_with_stale_actor_object(): void
    {
        $owner = User::factory()->verified()->create();
        $this->update($owner, 0, 'Initial');
        foreach ([['status' => AccountStatus::Suspended], ['status' => AccountStatus::Active, 'email_verified_at' => null]] as $change) {
            User::whereKey($owner->id)->update($change);
            try {
                $this->update($owner, 0, 'Initial');
                $this->fail('Le rejeu doit être interdit.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('content_revisions', 1);
            }
        }
        $this->assertDatabaseCount('api_idempotency', 1);
    }

    public function test_newer_profile_is_never_replaced_or_returned_as_the_old_snapshot(): void
    {
        $owner = User::factory()->verified()->create();
        $this->update($owner, 0, 'Ancien texte fictif');
        app(UpdateProfileService::class)->update($owner, new UpdateProfileData(1, ['bio' => ''], null));
        try {
            $this->update($owner, 0, 'Ancien texte fictif');
            $this->fail('Conflit attendu après nouvelle version.');
        } catch (ProfileVersionConflict) {
            $this->assertDatabaseHas('profiles', ['user_id' => $owner->id, 'bio' => '', 'lock_version' => 2]);
            $this->assertDatabaseCount('content_revisions', 2);
        }
    }

    public function test_storage_failure_rolls_back_profile_audit_and_key_then_same_key_can_retry(): void
    {
        $owner = User::factory()->verified()->create();
        DB::statement('ALTER TABLE api_idempotency ADD CONSTRAINT fixture_fail_intention CHECK (false)');
        try {
            $this->update($owner, 0, 'Texte non enregistré');
            $this->fail('Échec de stockage attendu.');
        } catch (IdempotencyStorageFailed $exception) {
            $this->assertNull($exception->getPrevious());
            $this->assertSame('Échec du stockage de l’intention.', $exception->getMessage());
        }
        $this->assertDatabaseCount('profiles', 0);
        $this->assertDatabaseCount('content_revisions', 0);
        $this->assertDatabaseCount('api_idempotency', 0);
        DB::statement('ALTER TABLE api_idempotency DROP CONSTRAINT fixture_fail_intention');
        $this->assertSame(1, $this->update($owner, 0, 'Texte non enregistré')->profile?->lock_version);
    }

    public function test_failed_business_command_and_outer_rollback_do_not_consume_key(): void
    {
        $owner = User::factory()->verified()->create();
        try {
            $this->update($owner, 7, 'Version invalide');
            $this->fail('Conflit attendu.');
        } catch (ProfileVersionConflict) {
            $this->assertDatabaseCount('api_idempotency', 0);
        }
        try {
            DB::transaction(function () use ($owner): void {
                $this->update($owner, 0, 'À annuler');
                $this->assertDatabaseCount('api_idempotency', 1);
                throw new RuntimeException('Rollback de fixture.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback de fixture.', $exception->getMessage());
        }
        $this->assertDatabaseCount('api_idempotency', 0);
        $this->assertDatabaseCount('profiles', 0);
        $this->assertDatabaseCount('content_revisions', 0);
        $this->assertSame(1, $this->update($owner, 0, 'À annuler')->profile?->lock_version);
    }

    public function test_foreign_or_malformed_stored_result_is_never_exposed_or_reexecuted(): void
    {
        $owner = User::factory()->verified()->create();
        $this->update($owner, 0, 'Test');
        foreach ([['references' => ['profile_id' => (string) Str::uuid()], 'version' => 1], ['body' => 'Donnée fictive incompatible']] as $corrupt) {
            DB::table('api_idempotency')->update(['response' => json_encode($corrupt, JSON_THROW_ON_ERROR)]);
            try {
                $this->update($owner, 0, 'Test');
                $this->fail('Résultat incompatible attendu.');
            } catch (IdempotencyStorageFailed $exception) {
                $this->assertNull($exception->getPrevious());
                $this->assertDatabaseCount('content_revisions', 1);
            }
        }
    }

    public function test_key_rotation_refuses_old_fingerprint_without_reexecuting(): void
    {
        $owner = User::factory()->verified()->create();
        $this->update($owner, 0, 'Test');
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        app()->forgetInstance('encrypter');
        $this->expectException(IdempotencyConflict::class);
        $this->update($owner, 0, 'Test');
    }

    public function test_prune_command_deletes_only_expired_intentions_without_business_data(): void
    {
        $this->freezeTime();
        $expired = User::factory()->verified()->create();
        $this->update($expired, 0, 'Ancien');
        $this->travel(23)->hours();
        $live = User::factory()->verified()->create();
        $this->update($live, 0, 'Récent');
        $this->travel(1)->hours();
        $command = $this->artisan('idempotency:prune');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput('1 intention(s) expirée(s) retirée(s).')->assertSuccessful();
        $command->run();
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->assertSame($live->id, DB::table('api_idempotency')->sole()->user_id);
        $this->assertDatabaseCount('profiles', 2);
        $this->assertDatabaseCount('content_revisions', 2);
        $events = array_values(array_filter(app(Schedule::class)->events(), fn ($event): bool => str_contains($event->command ?? '', 'idempotency:prune')));
        $this->assertCount(1, $events);
        $this->assertSame('*/5 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    public function test_deleted_target_is_not_recreated_or_exposed_on_replay(): void
    {
        $owner = User::factory()->verified()->create();
        $this->update($owner, 0, 'Ancien profil');
        Profile::whereKey($owner->id)->delete();
        try {
            $this->update($owner, 0, 'Ancien profil');
            $this->fail('Cible absente attendue.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('profiles', 0);
            $this->assertDatabaseCount('api_idempotency', 1);
            $this->assertDatabaseCount('content_revisions', 1);
        }
    }

    public function test_domain_authorization_runs_before_reading_a_stored_response(): void
    {
        $owner = User::factory()->verified()->create();
        $this->update($owner, 0, 'Initial');
        $reads = 0;
        DB::listen(function (QueryExecuted $query) use (&$reads): void {
            if (str_contains($query->sql, 'api_idempotency')) {
                $reads++;
            }
        });
        try {
            app(IdempotencyService::class)->execute($owner,
                new IdempotencyData('PATCH /api/v1/members/'.$owner->id.'/profile', new IdempotencyKey(self::KEY), ['lock_version' => 0, 'attributes' => ['bio' => 'Initial']]),
                function (User $current): void {
                    throw new AuthorizationException;
                },
                function (User $current): StoredCommandResult {
                    throw new RuntimeException('Opération interdite.');
                },
                function (User $current, StoredCommandResult $result): User {
                    throw new RuntimeException('Projection interdite.');
                });
        } catch (AuthorizationException) {
            $this->assertSame(0, $reads);
        }
    }

    private function update(User $owner, int $version, string $bio): User
    {
        return app(UpdateProfileService::class)->update($owner, new UpdateProfileData($version, ['bio' => $bio], null), new IdempotencyKey(self::KEY));
    }

    private function login(User $owner): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $owner->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}

<?php

namespace Tests\Integration;

use App\Data\Audit\ProfileRevisionData;
use App\Data\Identity\UpdateProfileData;
use App\Enums\Identity\AccountStatus;
use App\Exceptions\Audit\AuditStorageFailed;
use App\Exceptions\Identity\ProfileVersionConflict;
use App\Models\Profile;
use App\Models\Technology;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Identity\UpdateProfileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class AuditTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    public function test_real_profile_update_records_only_safe_metadata_and_server_identity_time(): void
    {
        $this->freezeTime();
        $actor = User::factory()->verified()->create();
        $tech = Technology::factory()->create();
        app(UpdateProfileService::class)->update($actor, new UpdateProfileData(0, ['bio' => 'texte confidentiel de fixture', 'country' => 'pays de fixture', 'github_url' => 'https://github.com/fixture'], [$tech->id]));
        $row = DB::table('content_revisions')->sole();
        $this->assertSame($actor->id, $row->actor_id);
        $this->assertSame($actor->id, $row->resource_id);
        $this->assertSame('profile', $row->resource_type);
        $this->assertSame('profile.updated', $row->action);
        $this->assertSame(1, $row->revision);
        $this->assertSame(now()->timestamp, strtotime($row->occurred_at));
        $this->assertEquals(['changed_fields' => ['bio', 'country', 'github_url', 'technology_ids'], 'profile_version' => 1, 'technology_count' => 1], json_decode($row->metadata, true, flags: JSON_THROW_ON_ERROR));
        foreach ([$actor->email, $actor->password, 'texte confidentiel', 'pays de fixture', 'https://github.com/fixture', $tech->id] as $private) {
            $this->assertStringNotContainsString($private, $row->metadata);
        }
        app(UpdateProfileService::class)->update($actor, new UpdateProfileData(1, ['bio' => ''], null));
        $this->assertSame([1, 2], DB::table('content_revisions')->orderBy('revision')->pluck('revision')->all());
        $this->assertStringNotContainsString('texte confidentiel', DB::table('content_revisions')->pluck('metadata')->implode(' '));
    }

    public function test_outer_rollback_removes_profile_pivot_and_audit_together(): void
    {
        $actor = User::factory()->verified()->create();
        $tech = Technology::factory()->create();
        try {
            DB::transaction(function () use ($actor, $tech): void {
                app(UpdateProfileService::class)->update($actor, new UpdateProfileData(0, ['bio' => 'À annuler'], [$tech->id]));
                $this->assertDatabaseCount('content_revisions', 1);
                throw new RuntimeException('Échec métier de fixture.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Échec métier de fixture.', $exception->getMessage());
        }
        $this->assertDatabaseCount('profiles', 0);
        $this->assertDatabaseCount('user_technologies', 0);
        $this->assertDatabaseCount('content_revisions', 0);
    }

    public function test_audit_failure_aborts_business_write_with_safe_exception(): void
    {
        $actor = User::factory()->verified()->create();
        Profile::factory()->for($actor)->create(['bio' => 'Original']);
        $tech = Technology::factory()->create();
        DB::statement('ALTER TABLE content_revisions ADD CONSTRAINT fixture_reject_audit CHECK (false)');
        try {
            app(UpdateProfileService::class)->update($actor, new UpdateProfileData(0, ['bio' => 'Ne pas conserver'], [$tech->id]));
            $this->fail('La panne d’audit doit annuler le métier.');
        } catch (AuditStorageFailed $exception) {
            $this->assertNull($exception->getPrevious());
            $this->assertSame('Échec du stockage de la révision.', $exception->getMessage());
        }
        $this->assertDatabaseHas('profiles', ['user_id' => $actor->id, 'bio' => 'Original', 'lock_version' => 0]);
        $this->assertDatabaseCount('content_revisions', 0);
        $this->assertDatabaseCount('user_technologies', 0);
    }

    public function test_conflict_and_denied_update_leave_no_audit_event(): void
    {
        $actor = User::factory()->verified()->create();
        Profile::factory()->for($actor)->create();
        try {
            app(UpdateProfileService::class)->update($actor, new UpdateProfileData(1, ['bio' => 'Interdit'], null));
            $this->fail('Conflit attendu.');
        } catch (ProfileVersionConflict) {
            $this->assertDatabaseCount('content_revisions', 0);
        }
        User::whereKey($actor->id)->update(['status' => AccountStatus::Suspended]);
        try {
            app(UpdateProfileService::class)->update($actor, new UpdateProfileData(0, ['bio' => 'Interdit'], null));
            $this->fail('Refus attendu.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('content_revisions', 0);
        }
    }

    public function test_purge_removes_legacy_values_without_copying_them_and_is_repeatable(): void
    {
        $owner = User::factory()->verified()->create();
        $moderator = User::factory()->verified()->moderator()->create();
        app(UpdateProfileService::class)->update($owner, new UpdateProfileData(0, ['bio' => 'Texte courant'], null));
        $other = User::factory()->verified()->create();
        app(UpdateProfileService::class)->update($other, new UpdateProfileData(0, ['bio' => 'Autre profil'], null));
        // Simule un ancien historique, jamais un vrai secret ni un chemin d'écriture applicatif.
        DB::table('content_revisions')->where('resource_id', $owner->id)->update(['metadata' => '{"legacy_body":"texte privé fictif à retirer"}']);
        $profile = Profile::findOrFail($owner->id);
        $this->assertSame(1, DB::transaction(fn () => app(AuditWriter::class)->redactProfileHistory($moderator, $profile)));
        $rows = DB::table('content_revisions')->where('resource_id', $owner->id)->orderBy('revision')->get();
        $this->assertCount(2, $rows);
        $original = $rows->get(0);
        $purge = $rows->get(1);
        $this->assertNotNull($original);
        $this->assertNotNull($purge);
        $this->assertSame('{}', $original->metadata);
        $this->assertNotNull($original->redacted_at);
        $this->assertSame($owner->id, $original->actor_id);
        $this->assertSame($moderator->id, $purge->actor_id);
        $this->assertSame('history.redacted', $purge->action);
        $this->assertEquals(['purged_revisions' => 1], json_decode($purge->metadata, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(0, DB::transaction(fn () => app(AuditWriter::class)->redactProfileHistory($moderator, $profile)));
        $this->assertDatabaseCount('content_revisions', 3);
        $this->assertNull(DB::table('content_revisions')->where('resource_id', $other->id)->value('redacted_at'));
        $this->assertStringNotContainsString('texte privé fictif', DB::table('content_revisions')->pluck('metadata')->implode(' '));
        $this->assertDatabaseHas('profiles', ['user_id' => $owner->id, 'bio' => 'Texte courant']);
        app(UpdateProfileService::class)->update($owner, new UpdateProfileData(1, ['bio' => 'Après purge'], null));
        $this->assertSame([1, 2, 3], DB::table('content_revisions')->where('resource_id', $owner->id)->orderBy('revision')->pluck('revision')->all());
    }

    public function test_purge_failure_restores_previous_metadata_and_writes_no_tombstone(): void
    {
        $owner = User::factory()->verified()->create();
        $moderator = User::factory()->verified()->moderator()->create();
        app(UpdateProfileService::class)->update($owner, new UpdateProfileData(0, ['bio' => 'Test'], null));
        $before = DB::table('content_revisions')->sole()->metadata;
        DB::statement("ALTER TABLE content_revisions ADD CONSTRAINT fixture_reject_redaction CHECK (action != 'history.redacted')");
        try {
            DB::transaction(fn () => app(AuditWriter::class)->redactProfileHistory($moderator, Profile::findOrFail($owner->id)));
            $this->fail('Échec de purge attendu.');
        } catch (AuditStorageFailed $exception) {
            $this->assertNull($exception->getPrevious());
        }
        $row = DB::table('content_revisions')->sole();
        $this->assertSame($before, $row->metadata);
        $this->assertNull($row->redacted_at);
    }

    public function test_purge_checks_current_moderator_rights_and_writer_refuses_foreign_actor(): void
    {
        $owner = User::factory()->verified()->create();
        $profile = Profile::factory()->for($owner)->create();
        $staleModerator = User::factory()->verified()->moderator()->create();
        User::whereKey($staleModerator->id)->update(['status' => AccountStatus::Suspended]);
        foreach ([$owner, $staleModerator, User::factory()->admin()->create()] as $actor) {
            try {
                DB::transaction(fn () => app(AuditWriter::class)->redactProfileHistory($actor, $profile));
                $this->fail('Refus attendu.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('content_revisions', 0);
            }
        }
        try {
            DB::transaction(fn () => app(AuditWriter::class)->profileUpdated($staleModerator, $profile, new ProfileRevisionData(['bio'])));
            $this->fail('Auteur tiers refusé.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('content_revisions', 0);
        }
    }

    public function test_writer_refuses_a_different_connection(): void
    {
        $owner = User::factory()->verified()->create();
        $profile = Profile::factory()->for($owner)->create();
        config(['database.connections.audit_other' => config('database.connections.pgsql')]);
        $profile->setConnection('audit_other');
        $this->expectException(LogicException::class);
        app(AuditWriter::class)->profileUpdated($owner, $profile, new ProfileRevisionData(['bio']));
    }

    public function test_http_cannot_forge_audit_fields_or_read_history(): void
    {
        $this->configureSpa('database');
        $owner = User::factory()->verified()->create();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $owner->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
        foreach (['actor_id' => $owner->id, 'occurred_at' => '2020-01-01', 'revision' => 9, 'metadata' => ['cookie' => 'fixture']] as $key => $value) {
            $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 0, 'bio' => 'Test', $key => $value])->assertUnprocessable();
        }
        $this->assertDatabaseCount('content_revisions', 0);
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 0, 'bio' => 'Test'])->assertOk()->assertJsonMissingPath('data.revisions');
        $this->browserRequest('GET', '/api/v1/members/'.$owner->handle)->assertOk()->assertJsonMissingPath('data.revisions');
        $this->browserRequest('GET', '/api/v1/content-revisions')->assertNotFound();
        $this->assertDatabaseCount('content_revisions', 1);
    }
}

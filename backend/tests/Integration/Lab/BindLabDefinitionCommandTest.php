<?php

declare(strict_types=1);

namespace Tests\Integration\Lab;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Identity\AccountStatus;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\Lab\RegistryFixtures;

final class BindLabDefinitionCommandTest extends PostgresTestCase
{
    use RefreshDatabase, RegistryFixtures;

    public function test_binding_is_idempotent_and_system_audit_does_not_invent_an_actor(): void
    {
        $version = $this->publishedVersion();
        $this->bind($version->id);
        $first = (array) DB::table('lab_definitions')->first();
        $this->bind($version->id);
        $this->assertSame($first, (array) DB::table('lab_definitions')->first());
        $this->assertSame(1, DB::table('lab_definitions')->count());
        $this->assertSame(1, DB::table('content_revisions')->where('action', 'lab.definition.bound')->count());
        $event = DB::table('content_revisions')->where('action', 'lab.definition.bound')->first();
        $this->assertNotNull($event);
        $this->assertNull($event->actor_id);
        $this->assertSame('lab_definition', $event->resource_type);
        $metadata = json_decode($event->metadata, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($metadata);
        $this->assertCount(2, $metadata);
        $this->assertSame($version->id, $metadata['version_id'] ?? null);
        $this->assertSame('b1-source-20261011', $metadata['release'] ?? null);
        $this->assertFalse(config('lab.enabled'));
        $this->assertSame(0, DB::table('lab_runs')->count());
        $this->assertSame(0, DB::table('lab_results')->count());
    }

    public function test_replay_preserves_a_deliberately_disabled_definition(): void
    {
        $version = $this->publishedVersion();
        $this->bind($version->id);
        DB::table('lab_definitions')->update(['enabled' => false]);
        $this->bind($version->id);
        $this->assertFalse(DB::table('lab_definitions')->value('enabled'));
        $this->assertSame(1, DB::table('content_revisions')->where('action', 'lab.definition.bound')->count());
    }

    public function test_conflicting_definition_is_not_overwritten_or_audited(): void
    {
        $version = $this->publishedVersion();
        $id = $this->definition($version, ['release' => 'another-release']);
        $before = (array) DB::table('lab_definitions')->find($id);
        $this->bind($version->id, success: false);
        $this->assertSame($before, (array) DB::table('lab_definitions')->find($id));
        $this->assertSame(0, DB::table('content_revisions')->where('action', 'lab.definition.bound')->count());
    }

    #[DataProvider('blockedBindings')]
    public function test_current_rights_and_visibility_are_rechecked_on_first_binding_and_replay(string $reason): void
    {
        $author = User::factory()->verified()->create();
        $source = HelpRequest::factory()->create(['author_id' => $author->id, 'state' => 'resolved']);
        $version = $this->publishedVersion($source);
        $capsule = Capsule::query()->findOrFail($version->capsule_id);
        $this->bind($version->id);
        $before = (array) DB::table('lab_definitions')->first();
        match ($reason) {
            'withdrawn' => DB::table('capsule_versions')->where('id', $version->id)->update(['state' => CapsuleVersionState::Withdrawn]),
            'capsule hidden' => DB::table('capsules')->where('id', $version->capsule_id)->update(['visibility' => CapsuleVisibility::Hidden]),
            'source hidden' => DB::table('help_requests')->where('id', $source->id)->update(['hidden_at' => now()]),
            'source draft' => DB::table('help_requests')->where('id', $source->id)->update(['state' => 'draft']),
            'source suspended' => DB::table('users')->where('id', $author->id)->update(['status' => AccountStatus::Suspended]),
            'source unverified' => DB::table('users')->where('id', $author->id)->update(['email_verified_at' => null]),
            'owner suspended' => DB::table('users')->where('id', $capsule->owner_id)->update(['status' => AccountStatus::Suspended]),
            'owner unverified' => DB::table('users')->where('id', $capsule->owner_id)->update(['email_verified_at' => null]),
            default => throw new \LogicException,
        };
        $this->bind($version->id, success: false);
        $this->assertSame($before, (array) DB::table('lab_definitions')->first());
        $this->assertSame(1, DB::table('content_revisions')->where('action', 'lab.definition.bound')->count());
    }

    /** @return iterable<string, array{string}> */
    public static function blockedBindings(): iterable
    {
        foreach (['withdrawn', 'capsule hidden', 'source hidden', 'source draft', 'source suspended', 'source unverified', 'owner suspended', 'owner unverified'] as $reason) {
            yield $reason => [$reason];
        }
    }

    public function test_legacy_digest_and_non_published_version_are_not_registered(): void
    {
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id]);
        $version = CapsuleVersion::factory()->published(User::factory()->verified()->moderator()->create())
            ->create(['capsule_id' => $capsule->id, 'content_digest' => null]);
        $this->bind($version->id, success: false);
        $draft = CapsuleVersion::factory()->create(['capsule_id' => $capsule->id, 'version_label' => '1.0.1']);
        $this->bind($draft->id, success: false);
        $this->assertSame(0, DB::table('lab_definitions')->count());
    }

    public function test_only_uuid_and_fixed_release_are_accepted_without_echoing_untrusted_input(): void
    {
        $version = $this->publishedVersion();
        $first = $this->artisan('lab:bind-definition', ['version' => 'https://untrusted.invalid/fake', 'release' => 'b1-source-20261011']);
        $this->assertInstanceOf(PendingCommand::class, $first);
        $first->expectsOutput('Liaison refusée : UUID ou release non admis.')->assertFailed();
        $this->assertSame(1, $first->run());
        $second = $this->artisan('lab:bind-definition', ['version' => $version->id, 'release' => 'untrusted.class']);
        $this->assertInstanceOf(PendingCommand::class, $second);
        $second->expectsOutput('Liaison refusée : UUID ou release non admis.')->assertFailed();
        $this->assertSame(1, $second->run());
        $this->assertSame(0, DB::table('lab_definitions')->count());
    }

    public function test_private_source_is_refused_before_the_first_binding(): void
    {
        $source = HelpRequest::factory()->create(['author_id' => User::factory()->verified()->create()->id, 'state' => 'resolved']);
        $version = $this->publishedVersion($source);
        DB::table('help_requests')->where('id', $source->id)->update(['hidden_at' => now()]);
        $this->bind($version->id, success: false);
        $this->assertSame(0, DB::table('lab_definitions')->count());
    }

    public function test_restored_owner_can_replay_binding_without_another_audit_or_run(): void
    {
        $version = $this->publishedVersion();
        $capsule = Capsule::query()->findOrFail($version->capsule_id);
        $this->bind($version->id);
        DB::table('users')->where('id', $capsule->owner_id)->update(['status' => AccountStatus::Suspended]);
        $this->bind($version->id, success: false);
        DB::table('users')->where('id', $capsule->owner_id)->update(['status' => AccountStatus::Active]);
        $this->bind($version->id);
        $this->assertSame(1, DB::table('content_revisions')->where('action', 'lab.definition.bound')->count());
        $this->assertSame(0, DB::table('lab_runs')->count());
    }

    public function test_audit_storage_failure_rolls_back_the_binding(): void
    {
        $version = $this->publishedVersion();
        DB::statement("ALTER TABLE content_revisions ADD CONSTRAINT b33_test_audit_failure CHECK (action <> 'lab.definition.bound')");
        $command = $this->artisan('lab:bind-definition', ['version' => $version->id, 'release' => 'b1-source-20261011']);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput('Liaison refusée : stockage applicatif indisponible.')->assertFailed();
        $this->assertSame(1, $command->run());
        $this->assertSame(0, DB::table('lab_definitions')->count());
        $this->assertSame(0, DB::table('content_revisions')->where('action', 'lab.definition.bound')->count());
    }

    private function bind(string $versionId, bool $success = true): void
    {
        $command = $this->artisan('lab:bind-definition', ['version' => $versionId, 'release' => 'b1-source-20261011']);
        $this->assertInstanceOf(PendingCommand::class, $command);
        if ($success) {
            $command->expectsOutput('Définition liée à la release fixe ; aucun run lancé.')->assertSuccessful();
        } else {
            $command->expectsOutput('Liaison refusée : version, provenance ou manifeste indisponible.')->assertFailed();
        }
        $this->assertSame($success ? 0 : 1, $command->run());
    }
}

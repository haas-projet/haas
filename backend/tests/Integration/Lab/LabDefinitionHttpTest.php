<?php

declare(strict_types=1);

namespace Tests\Integration\Lab;

use App\Data\Lab\LabSelectionData;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Identity\AccountStatus;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\HelpRequest;
use App\Models\User;
use App\Queries\Lab\LabDefinitionQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\PostgresTestCase;
use Tests\Support\Lab\RegistryFixtures;
use Tests\Support\SpaHttpRequests;

final class LabDefinitionHttpTest extends PostgresTestCase
{
    use RefreshDatabase, RegistryFixtures, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa();
        config(['lab.enabled' => true]);
    }

    public function test_public_capability_is_versioned_bounded_and_does_not_create_runs_or_results(): void
    {
        $version = $this->publishedVersion();
        $definition = $this->definition($version);
        $response = $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/lab-definition');
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.version_id', $version->id)->assertJsonPath('data.available', true)
            ->assertJsonPath('data.definition.id', $definition)->assertJsonCount(4, 'data.definition.scenarios');
        $this->assertSame(['version_id', 'available', 'definition'], array_keys($response->json('data')));
        $this->assertSame(['id', 'release', 'module_version', 'code_digest', 'suite_digest', 'version_digest', 'scenarios'], array_keys($response->json('data.definition')));
        $this->assertSame(['nominal_event', 'duplicate_event', 'distinct_events', 'invalid_event'], array_column($response->json('data.definition.scenarios'), 'key'));
        $this->assertSame(0, DB::table('lab_runs')->count());
        $this->assertSame(0, DB::table('lab_results')->count());
    }

    #[DataProvider('unavailableDefinitions')]
    public function test_switch_and_missing_or_mismatched_definition_fail_closed(string $reason): void
    {
        $version = $this->publishedVersion();
        if ($reason !== 'missing') {
            $changes = match ($reason) {
                'disabled' => ['enabled' => false],
                'release' => ['release' => 'not-approved'],
                'code' => ['code_digest' => str_repeat('0', 64)],
                'suite' => ['suite_digest' => str_repeat('0', 64)],
                'version' => ['version_digest' => str_repeat('0', 64)],
                default => [],
            };
            $this->definition($version, $changes);
        }
        if ($reason === 'switch' || $reason === 'non-boolean-switch') {
            config(['lab.enabled' => $reason === 'switch' ? false : 'true']);
        }
        $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/lab-definition')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.available', false)->assertJsonPath('data.definition', null);
        $this->expectException(ServiceUnavailableHttpException::class);
        app(LabDefinitionQuery::class)->selection(new LabSelectionData(['capsule_version_id' => $version->id, 'scenario' => 'nominal_event']));
    }

    /** @return iterable<string, array{string}> */
    public static function unavailableDefinitions(): iterable
    {
        foreach (['missing', 'disabled', 'switch', 'non-boolean-switch', 'release', 'code', 'suite', 'version'] as $reason) {
            yield $reason => [$reason];
        }
    }

    public function test_definition_is_not_inherited_by_another_published_version(): void
    {
        $first = $this->publishedVersion();
        $this->definition($first);
        $second = $this->publishedVersion(capsule: Capsule::findOrFail($first->capsule_id));
        $this->browserRequest('GET', '/api/v1/versions/'.$second->id.'/lab-definition')
            ->assertOk()->assertJsonPath('data.available', false);
        $this->browserRequest('GET', '/api/v1/versions/'.$first->id.'/lab-definition')
            ->assertOk()->assertJsonPath('data.available', true);
    }

    public function test_legacy_publication_without_known_digest_has_no_capacity(): void
    {
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id]);
        $version = CapsuleVersion::factory()->published(User::factory()->verified()->moderator()->create())
            ->create(['capsule_id' => $capsule->id, 'content_digest' => null]);
        $this->definition($version);
        $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/lab-definition')
            ->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.definition', null);
        $this->assertNull(CapsuleVersion::query()->findOrFail($version->id)->content_digest);
    }

    public function test_drafts_and_versions_in_review_are_not_lab_capabilities(): void
    {
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id]);
        $draft = CapsuleVersion::factory()->create(['capsule_id' => $capsule->id]);
        $review = CapsuleVersion::factory()->inReview(User::factory()->verified()->moderator()->create())
            ->create(['capsule_id' => $capsule->id, 'version_label' => '1.0.1']);
        foreach ([$draft, $review] as $version) {
            $this->definition($version);
            $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/lab-definition')->assertNotFound();
        }
    }

    #[DataProvider('hiddenTargets')]
    public function test_hidden_capsules_and_non_published_versions_are_not_disclosed(string $reason): void
    {
        $version = $this->publishedVersion();
        $this->definition($version);
        if ($reason === 'capsule') {
            DB::table('capsules')->where('id', $version->capsule_id)->update(['visibility' => CapsuleVisibility::Hidden]);
        } else {
            DB::table('capsule_versions')->where('id', $version->id)->update(['state' => CapsuleVersionState::Withdrawn]);
        }
        $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/lab-definition')->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
    }

    /** @return iterable<string, array{string}> */
    public static function hiddenTargets(): iterable
    {
        yield 'hidden capsule' => ['capsule'];
        yield 'withdrawn version' => ['version'];
    }

    #[DataProvider('privateSources')]
    public function test_source_becoming_private_disables_lab_without_hiding_documentary_capsule(string $reason): void
    {
        $author = User::factory()->verified()->create();
        $source = HelpRequest::factory()->create(['author_id' => $author->id, 'state' => 'resolved']);
        $version = $this->publishedVersion($source);
        $this->definition($version);
        $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/lab-definition')->assertOk()->assertJsonPath('data.available', true);
        match ($reason) {
            'hidden' => DB::table('help_requests')->where('id', $source->id)->update(['hidden_at' => now()]),
            'draft' => DB::table('help_requests')->where('id', $source->id)->update(['state' => 'draft']),
            'suspended' => DB::table('users')->where('id', $author->id)->update(['status' => AccountStatus::Suspended]),
            'unverified' => DB::table('users')->where('id', $author->id)->update(['email_verified_at' => null]),
            default => throw new \LogicException,
        };
        $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/lab-definition')->assertNotFound();
        $capsule = Capsule::findOrFail($version->capsule_id);
        $this->assertSame(CapsuleVisibility::Visible, $capsule->visibility);
        $this->assertSame(CapsuleVersionState::Published, CapsuleVersion::query()->findOrFail($version->id)->state);
    }

    /** @return iterable<string, array{string}> */
    public static function privateSources(): iterable
    {
        foreach (['hidden', 'draft', 'suspended', 'unverified'] as $reason) {
            yield $reason => [$reason];
        }
    }

    #[DataProvider('unavailableOwners')]
    public function test_current_owner_status_gates_lab_and_restoration_does_not_launch_a_run(string $reason): void
    {
        $version = $this->publishedVersion();
        $this->definition($version);
        $capsule = Capsule::findOrFail($version->capsule_id);
        $owner = User::query()->findOrFail($capsule->owner_id);
        if ($reason === 'suspended') {
            DB::table('users')->where('id', $owner->id)->update(['status' => AccountStatus::Suspended]);
        } else {
            DB::table('users')->where('id', $owner->id)->update(['email_verified_at' => null]);
        }
        $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/lab-definition')->assertNotFound();
        $this->assertSame(CapsuleVisibility::Visible, Capsule::query()->findOrFail($capsule->id)->visibility);
        $this->assertSame(CapsuleVersionState::Published, CapsuleVersion::query()->findOrFail($version->id)->state);
        DB::table('users')->where('id', $owner->id)->update(['status' => AccountStatus::Active, 'email_verified_at' => $owner->email_verified_at]);
        $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/lab-definition')->assertOk()->assertJsonPath('data.available', true);
        $this->assertSame(0, DB::table('lab_runs')->count());
        $this->assertSame(0, DB::table('lab_results')->count());
    }

    /** @return iterable<string, array{string}> */
    public static function unavailableOwners(): iterable
    {
        yield 'suspended' => ['suspended'];
        yield 'unverified' => ['unverified'];
    }

    public function test_query_and_body_cannot_select_a_runner_or_send_code(): void
    {
        $version = $this->publishedVersion();
        $this->definition($version);
        $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/lab-definition?runner[]=evil')->assertUnprocessable();
        $this->browserRequest('GET', '/api/v1/versions/'.$version->id.'/lab-definition', ['code' => 'untrusted'])->assertUnprocessable();
    }
}

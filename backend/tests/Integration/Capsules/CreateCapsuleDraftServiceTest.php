<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Data\Capsules\CapsuleDraftData;
use App\Data\Capsules\TechnologyAttachmentData;
use App\Data\Capsules\VersionDraftData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\Capsules\CapsuleSourceKind;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Identity\Role;
use App\Models\Capsules\Capsule;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\Technology;
use App\Models\User;
use App\Services\Capsules\CreateCapsuleDraftService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\PostgresTestCase;

final class CreateCapsuleDraftServiceTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_help_request_author_can_create_a_draft_from_their_resolved_request(): void
    {
        [$author, $request] = $this->resolvedHelpRequest();
        $tech = Technology::factory()->create();

        $result = app(CreateCapsuleDraftService::class)->handle(
            actor: $author,
            data: new CapsuleDraftData(
                slug: 'contrats-paiement',
                sourceKind: CapsuleSourceKind::HelpRequest,
                sourceRequestId: $request->id,
                editorialOrigin: null,
                version: $this->validVersion([$tech->id]),
            ),
            key: new IdempotencyKey((string) Str::uuid()),
        );

        $capsule = Capsule::findOrFail($result['capsule_id']);
        $this->assertSame($author->id, $capsule->owner_id);
        $this->assertSame($request->id, $capsule->source_request_id);
        $this->assertSame(CapsuleVersionState::Draft, $capsule->versions()->firstOrFail()->state);
        $this->assertDatabaseHas('capsule_contributors', [
            'version_id' => $result['version_id'],
            'user_id' => $author->id,
            'contribution_role' => 'diagnosis',
        ]);
        $this->assertDatabaseHas('content_revisions', [
            'actor_id' => $author->id,
            'resource_type' => 'capsule_version',
            'resource_id' => $result['version_id'],
            'action' => 'capsule.draft.created',
        ]);
    }

    public function test_a_third_party_cannot_create_a_draft_from_someone_elses_request(): void
    {
        [$author, $request] = $this->resolvedHelpRequest();
        $stranger = User::factory()->verified()->create();

        $this->expectException(AuthorizationException::class);

        app(CreateCapsuleDraftService::class)->handle(
            actor: $stranger,
            data: new CapsuleDraftData(
                slug: 'nouveau-slug',
                sourceKind: CapsuleSourceKind::HelpRequest,
                sourceRequestId: $request->id,
                editorialOrigin: null,
                version: $this->validVersion(),
            ),
            key: new IdempotencyKey((string) Str::uuid()),
        );
    }

    public function test_unresolved_help_request_is_rejected_with_422(): void
    {
        $author = User::factory()->verified()->create();
        $request = HelpRequest::factory()->create(['author_id' => $author->id]);

        $this->expectException(ValidationException::class);

        app(CreateCapsuleDraftService::class)->handle(
            actor: $author,
            data: new CapsuleDraftData(
                slug: 'nouveau-slug',
                sourceKind: CapsuleSourceKind::HelpRequest,
                sourceRequestId: $request->id,
                editorialOrigin: null,
                version: $this->validVersion(),
            ),
            key: new IdempotencyKey((string) Str::uuid()),
        );
    }

    public function test_editorial_source_is_denied_for_regular_members(): void
    {
        $member = User::factory()->verified()->create();

        $this->expectException(AuthorizationException::class);

        app(CreateCapsuleDraftService::class)->handle(
            actor: $member,
            data: new CapsuleDraftData(
                slug: 'brique-demo',
                sourceKind: CapsuleSourceKind::Editorial,
                sourceRequestId: null,
                editorialOrigin: 'Démonstration éditoriale',
                version: $this->validVersion(),
            ),
            key: new IdempotencyKey((string) Str::uuid()),
        );
    }

    public function test_editorial_source_is_accepted_for_moderators(): void
    {
        $moderator = User::factory()->verified()->create(['role' => Role::Moderator]);

        $result = app(CreateCapsuleDraftService::class)->handle(
            actor: $moderator,
            data: new CapsuleDraftData(
                slug: 'brique-demo',
                sourceKind: CapsuleSourceKind::Editorial,
                sourceRequestId: null,
                editorialOrigin: 'Démonstration éditoriale B1',
                version: $this->validVersion(),
            ),
            key: new IdempotencyKey((string) Str::uuid()),
        );

        $capsule = Capsule::findOrFail($result['capsule_id']);
        $this->assertSame('Démonstration éditoriale B1', $capsule->editorial_origin);
        $this->assertNull($capsule->source_request_id);
    }

    public function test_same_key_and_payload_produces_a_single_write_then_replays(): void
    {
        [$author, $request] = $this->resolvedHelpRequest();
        $tech = Technology::factory()->create();
        $data = new CapsuleDraftData(
            slug: 'rejeu-slug',
            sourceKind: CapsuleSourceKind::HelpRequest,
            sourceRequestId: $request->id,
            editorialOrigin: null,
            version: $this->validVersion([$tech->id]),
        );
        $key = new IdempotencyKey((string) Str::uuid());

        $first = app(CreateCapsuleDraftService::class)->handle($author, $data, $key);
        $second = app(CreateCapsuleDraftService::class)->handle($author, $data, $key);

        $this->assertSame($first['capsule_id'], $second['capsule_id']);
        $this->assertSame($first['version_id'], $second['version_id']);
        $this->assertDatabaseCount('capsules', 1);
        $this->assertDatabaseCount('capsule_versions', 1);
        $this->assertDatabaseCount('content_revisions', 1);
    }

    public function test_rollback_removes_capsule_version_and_audit_together(): void
    {
        [$author, $request] = $this->resolvedHelpRequest();
        $tech = Technology::factory()->create();
        $data = new CapsuleDraftData(
            slug: 'rollback-slug',
            sourceKind: CapsuleSourceKind::HelpRequest,
            sourceRequestId: $request->id,
            editorialOrigin: null,
            version: $this->validVersion([$tech->id]),
        );

        try {
            DB::transaction(function () use ($author, $data): void {
                app(CreateCapsuleDraftService::class)->handle($author, $data, new IdempotencyKey((string) Str::uuid()));
                throw new \RuntimeException('annule explicitement');
            });
        } catch (\RuntimeException) {
            // attendu
        }

        $this->assertDatabaseCount('capsules', 0);
        $this->assertDatabaseCount('capsule_versions', 0);
        $this->assertDatabaseCount('content_revisions', 0);
    }

    /**
     * @return array{User, HelpRequest}
     */
    private function resolvedHelpRequest(): array
    {
        $author = User::factory()->verified()->create();
        $request = HelpRequest::factory()->create(['author_id' => $author->id]);
        $proposal = Proposal::factory()->create(['request_id' => $request->id, 'author_id' => $author->id]);
        Resolution::factory()->create([
            'request_id' => $request->id,
            'proposal_id' => $proposal->id,
            'accepted_by' => $author->id,
        ]);

        return [$author, $request];
    }

    /** @param list<string> $technologyIds */
    private function validVersion(array $technologyIds = []): VersionDraftData
    {
        $tech = [];
        foreach ($technologyIds as $id) {
            $tech[] = new TechnologyAttachmentData($id, '^1.0');
        }

        return new VersionDraftData(
            versionLabel: '1.0.0',
            body: "## Procédure\n\nÉtape documentée pour le brouillon.",
            limits: 'Portée réduite au scénario cité.',
            technologies: $tech,
        );
    }
}

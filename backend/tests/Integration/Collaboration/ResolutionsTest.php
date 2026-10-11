<?php

namespace Tests\Integration\Collaboration;

use App\Enums\Identity\Role;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class ResolutionsTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    private const string KEY = 'b1900000-1111-4444-8888-123456789abc';

    private const string NOTE = 'Validation privee fictive B19 : le resultat est declare dans mon contexte.';

    private const string ENVIRONMENT = 'Environnement prive fictif B19 avec PostgreSQL 17';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_owner_accepts_current_content_once_and_other_proposals_remain_unchanged(): void
    {
        $owner = User::factory()->verified()->create();
        $author = User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'open']);
        $parent->technologies()->attach(Technology::factory()->create());
        $this->login($author);
        $created = $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/proposals', self::proposalPayload(), ['Idempotency-Key' => self::KEY])->assertCreated();
        $proposal = Proposal::whereKey($created->json('data.id'))->firstOrFail();
        $proposalBefore = $proposal->refresh()->getAttributes();
        $other = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => User::factory()->verified(), 'lock_version' => 7]);
        $otherBefore = $other->refresh()->getAttributes();
        $parentBefore = $parent->refresh()->getAttributes();
        $payload = self::payload($proposal, $parent->lock_version, ['validation_note' => self::NOTE.' <script>texte</script> ![image](https://example.test/no-fetch)']);
        $this->login($owner);
        Http::preventStrayRequests();
        $first = $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => self::KEY])
            ->assertOk()->assertJsonPath('data.request_id', $parent->id)->assertJsonPath('data.proposal_id', $proposal->id)
            ->assertJsonPath('data.proposal_version', 1)->assertJsonPath('data.request_lock_version', 3)
            ->assertJsonPath('data.environment', self::ENVIRONMENT)->assertJsonPath('data.validation_note', $payload['validation_note'])
            ->assertJsonPath('data.accepted_by.id', $owner->id)->assertJsonPath('data.accepted_by.handle', $owner->handle)
            ->assertJsonPath('data.self_resolution', false)->assertJsonPath('data.revoked_at', null)
            ->assertJsonMissingPath('data.accepted_by.email')->assertJsonMissingPath('data.accepted_by.name')
            ->assertHeader('Cache-Control', 'no-store, private');
        $id = $first->json('data.id');
        $this->assertIsString($id);
        $this->assertNotNull($first->json('data.accepted_at'));
        $this->assertStringNotContainsString('<script>', (string) $first->json('data.validation_note_html'));
        $this->assertStringNotContainsString('<img', (string) $first->json('data.validation_note_html'));
        Http::assertNothingSent();

        $this->browserRequest('POST', '/api/v1/requests/'.strtoupper($parent->id).'/resolve', $payload, ['Idempotency-Key' => strtoupper(self::KEY)])
            ->assertOk()->assertExactJson($first->json());
        $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
        $this->browserRequest('POST', $this->resolvePath($parent), array_replace($payload, ['environment' => 'Une autre intention de validation fictive.']), ['Idempotency-Key' => self::KEY])
            ->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $currentParent = $parent->refresh()->getAttributes();
        $this->assertSame('resolved', $parent->state->value);
        $this->assertSame(3, $parent->lock_version);
        foreach (['state', 'lock_version', 'updated_at'] as $mutable) {
            unset($parentBefore[$mutable], $currentParent[$mutable]);
        }
        $this->assertSame($parentBefore, $currentParent);
        $currentProposal = $proposal->refresh()->getAttributes();
        $this->assertSame('accepted', $proposal->state->value);
        $this->assertSame(1, $proposal->lock_version);
        foreach (['state', 'updated_at'] as $mutable) {
            unset($proposalBefore[$mutable], $currentProposal[$mutable]);
        }
        $this->assertSame($proposalBefore, $currentProposal);
        $this->assertSame($otherBefore, $other->refresh()->getAttributes());
        $this->assertSame('proposed', $other->state->value);
        $this->assertDatabaseCount('resolutions', 1);
        $this->assertDatabaseCount('proposal_revisions', 1);
        $this->assertDatabaseCount('content_revisions', 4);
        $this->assertDatabaseCount('api_idempotency', 2);
        $this->assertDatabaseHas('content_revisions', ['resource_id' => $parent->id, 'actor_id' => $owner->id, 'action' => 'help_request.resolved']);
        $this->assertDatabaseHas('content_revisions', ['resource_id' => $id, 'actor_id' => $owner->id, 'action' => 'resolution.accepted']);
        $this->assertSame(0, DB::table('content_revisions')->where('action', 'proposal.accepted')->count());
        $this->assertDatabaseHas('notification_outbox', ['event_id' => $id, 'recipient_id' => $author->id, 'kind' => 'resolution.accepted', 'delivered_at' => null]);
        $this->assertDatabaseCount('internal_notifications', 0);
        $this->assertPrivateContentAbsentFromTechnicalStorage();
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->browserRequest('GET', $this->activePath($parent))->assertOk()->assertExactJson($first->json())->assertHeader('Cache-Control', 'no-store, private');
        $this->login($author);
        $this->browserRequest('PATCH', '/api/v1/proposals/'.$proposal->id, ['fix' => 'Une modification interdite après la résolution.', 'lock_version' => 1], ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/proposals', self::proposalPayload(), ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
        $this->login(User::findOrFail($other->author_id));
        $this->browserRequest('PATCH', '/api/v1/proposals/'.$other->id, ['fix' => 'Une autre proposition reste figée tant que la demande est résolue.', 'lock_version' => 7], ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertSame($otherBefore, $other->refresh()->getAttributes());
    }

    #[DataProvider('roles')]
    public function test_third_party_cannot_resolve_even_as_admin_or_moderator(Role $role): void
    {
        [, , $parent, $proposal] = $this->fixture();
        $parentBefore = $parent->refresh()->getAttributes();
        $proposalBefore = $proposal->refresh()->getAttributes();
        $this->login(User::factory()->verified()->create(['role' => $role]));
        $this->browserRequest('POST', $this->resolvePath($parent), self::payload($proposal, $parent->lock_version), ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertSame($parentBefore, $parent->refresh()->getAttributes());
        $this->assertSame($proposalBefore, $proposal->refresh()->getAttributes());
        $this->assertNoResolutionEffects();
    }

    /** @return iterable<string, array{Role}> */
    public static function roles(): iterable
    {
        foreach (Role::cases() as $role) {
            yield $role->value => [$role];
        }
    }

    public function test_admin_who_is_the_owner_has_the_same_permission_as_any_owner(): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        $owner->forceFill(['role' => Role::Admin])->save();
        $this->login($owner);
        $this->browserRequest('POST', $this->resolvePath($parent), self::payload($proposal, $parent->lock_version), ['Idempotency-Key' => self::KEY])
            ->assertOk()->assertJsonPath('data.accepted_by.id', $owner->id);
    }

    public function test_foreign_proposal_is_not_accepted_or_attached_to_the_parent(): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        [, , $foreignParent, $foreignProposal] = $this->fixture();
        $parentBefore = $parent->refresh()->getAttributes();
        $foreignBefore = $foreignProposal->refresh()->getAttributes();
        $this->login($owner);
        $this->browserRequest('POST', $this->resolvePath($parent), self::payload($foreignProposal, $parent->lock_version), ['Idempotency-Key' => self::KEY])->assertNotFound();
        $this->assertSame($parentBefore, $parent->refresh()->getAttributes());
        $this->assertSame($foreignBefore, $foreignProposal->refresh()->getAttributes());
        $this->assertSame('in_progress', $foreignParent->refresh()->state->value);
        $this->assertSame('proposed', $proposal->refresh()->state->value);
        $this->assertNoResolutionEffects();
    }

    #[DataProvider('unavailableBeforeCreation')]
    public function test_currently_hidden_parent_or_ineligible_proposal_is_not_resolved(string $kind): void
    {
        [$owner, $author, $parent, $proposal] = $this->fixture();
        match ($kind) {
            'parent_hidden' => $parent->forceFill(['hidden_at' => now()])->save(),
            'proposal_hidden' => $proposal->forceFill(['hidden_at' => now()])->save(),
            'author_suspended' => $author->forceFill(['status' => 'suspended'])->save(),
            'author_unverified' => $author->forceFill(['email_verified_at' => null])->save(),
            default => throw new \InvalidArgumentException('Fixture initiale B19 inconnue.'),
        };
        $parentBefore = $parent->refresh()->getAttributes();
        $proposalBefore = $proposal->refresh()->getAttributes();
        $this->login($owner);
        $this->browserRequest('POST', $this->resolvePath($parent), self::payload($proposal, $parent->lock_version), ['Idempotency-Key' => self::KEY])->assertNotFound();
        $this->assertSame($parentBefore, $parent->refresh()->getAttributes());
        $this->assertSame($proposalBefore, $proposal->refresh()->getAttributes());
        $this->assertNoResolutionEffects();
    }

    /** @return iterable<string, array{string}> */
    public static function unavailableBeforeCreation(): iterable
    {
        foreach (['parent_hidden', 'proposal_hidden', 'author_suspended', 'author_unverified'] as $kind) {
            yield $kind => [$kind];
        }
    }

    public function test_self_resolution_is_explicitly_labelled_and_does_not_notify_the_same_person(): void
    {
        [$owner, , $parent, $proposal] = $this->fixture(selfResolution: true);
        $this->login($owner);
        $response = $this->browserRequest('POST', $this->resolvePath($parent), self::payload($proposal, $parent->lock_version), ['Idempotency-Key' => self::KEY])
            ->assertOk()->assertJsonPath('data.self_resolution', true)->assertJsonPath('data.accepted_by.id', $owner->id);
        $this->assertDatabaseHas('resolutions', ['id' => $response->json('data.id'), 'accepted_by' => $owner->id, 'proposal_id' => $proposal->id]);
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertDatabaseCount('content_revisions', 2);
        $this->assertSame(3, $proposal->refresh()->lock_version);
    }

    #[DataProvider('ineligibleProposalStates')]
    public function test_already_accepted_or_not_selected_proposal_conflicts_without_a_new_resolution(string $state): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        $proposal->forceFill(['state' => $state])->save();
        $this->login($owner);
        $this->browserRequest('POST', $this->resolvePath($parent), self::payload($proposal, $parent->lock_version), ['Idempotency-Key' => self::KEY])->assertStatus(409);
        $this->assertSame($state, $proposal->refresh()->state->value);
        $this->assertSame(4, $parent->refresh()->lock_version);
        $this->assertNoResolutionEffects();
    }

    /** @return iterable<string, array{string}> */
    public static function ineligibleProposalStates(): iterable
    {
        yield 'accepted' => ['accepted'];
        yield 'not selected' => ['not_selected'];
    }

    #[DataProvider('forbiddenParentStates')]
    public function test_draft_or_archived_parent_refuses_resolution_by_its_owner(string $state): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        $parent->forceFill(['state' => $state])->save();
        $this->login($owner);
        $this->browserRequest('POST', $this->resolvePath($parent), self::payload($proposal, $parent->lock_version), ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertNoResolutionEffects();
    }

    /** @return iterable<string, array{string}> */
    public static function forbiddenParentStates(): iterable
    {
        yield 'draft' => ['draft'];
        yield 'archived' => ['archived'];
    }

    #[DataProvider('staleVersionFields')]
    public function test_parent_or_proposal_version_conflict_keeps_both_resources_unchanged(string $field): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        $parentBefore = $parent->refresh()->getAttributes();
        $proposalBefore = $proposal->refresh()->getAttributes();
        $this->login($owner);
        $payload = self::payload($proposal, $parent->lock_version, [$field => $field === 'lock_version' ? 3 : 2]);
        $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => self::KEY])->assertStatus(409);
        $this->assertSame($parentBefore, $parent->refresh()->getAttributes());
        $this->assertSame($proposalBefore, $proposal->refresh()->getAttributes());
        $this->assertNoResolutionEffects();
    }

    /** @return iterable<string, array{string}> */
    public static function staleVersionFields(): iterable
    {
        yield 'parent' => ['lock_version'];
        yield 'proposal' => ['proposal_lock_version'];
    }

    #[DataProvider('maximumVersions')]
    public function test_only_parent_version_is_incremented_at_integer_boundaries(int $parentVersion, int $proposalVersion): void
    {
        [$owner, , $parent, $proposal] = $this->fixture(parentVersion: $parentVersion, proposalVersion: $proposalVersion);
        $this->login($owner);
        $this->browserRequest('POST', $this->resolvePath($parent), self::payload($proposal, $parentVersion), ['Idempotency-Key' => self::KEY])
            ->assertOk()->assertJsonPath('data.request_lock_version', $parentVersion + 1)->assertJsonPath('data.proposal_version', $proposalVersion);
        $this->assertSame($parentVersion + 1, $parent->refresh()->lock_version);
        $this->assertSame($proposalVersion, $proposal->refresh()->lock_version);
        $this->assertDatabaseCount('proposal_revisions', 0);
    }

    /** @return iterable<string, array{int, int}> */
    public static function maximumVersions(): iterable
    {
        yield 'parent final increment' => [2147483646, 3];
        yield 'proposal maximum unchanged' => [4, 2147483647];
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_or_protected_input_is_rejected_without_effect(array $changes, string $field): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        $this->login($owner);
        $this->browserRequest('POST', $this->resolvePath($parent), self::payload($proposal, $parent->lock_version, $changes), ['Idempotency-Key' => self::KEY])
            ->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
        $this->assertNoResolutionEffects();
        $this->assertSame(4, $parent->refresh()->lock_version);
        $this->assertSame('proposed', $proposal->refresh()->state->value);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): iterable
    {
        foreach (['lock_version', 'proposal_lock_version'] as $field) {
            foreach (['3', 3.5, true, null, 0, ['3']] as $index => $value) {
                yield $field.' type '.$index => [[$field => $value], $field];
            }
        }
        yield 'parent overflow' => [['lock_version' => 2147483647], 'lock_version'];
        yield 'proposal overflow' => [['proposal_lock_version' => 2147483648], 'proposal_lock_version'];
        yield 'invalid UUID' => [['proposal_id' => 'not-a-uuid'], 'proposal_id'];
        yield 'blank environment' => [['environment' => '   '], 'environment'];
        yield 'oversized environment' => [['environment' => str_repeat('é', 256)], 'environment'];
        yield 'controlled environment' => [['environment' => "\x01".self::ENVIRONMENT], 'environment'];
        yield 'short note' => [['validation_note' => str_repeat('a', 19)], 'validation_note'];
        yield 'oversized note' => [['validation_note' => str_repeat('é', 4001)], 'validation_note'];
        yield 'invisible note' => [['validation_note' => str_repeat("\u{200B}", 30)], 'validation_note'];
        yield 'link only' => [['validation_note' => '[Validation assez longue](https://example.test/link-only)'], 'validation_note'];
        yield 'controlled edge note' => [['validation_note' => "\x01".self::NOTE], 'validation_note'];
        yield 'secret note' => [['validation_note' => 'api_key='.str_repeat('a', 40)], 'validation_note'];
        foreach (['accepted_by' => 'b1900000-1111-4444-8888-123456789abd', 'accepted_at' => null, 'revoked_at' => null, 'request_id' => 'b1900000-1111-4444-8888-123456789abd', 'proposal_version' => 3, 'state' => 'resolved', 'self_resolution' => true, 'unexpected' => true] as $field => $value) {
            yield 'server field '.$field => [[$field => $value], $field];
        }
    }

    public function test_useful_note_and_environment_are_normalized_after_raw_control_validation(): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        $this->login($owner);
        $note = str_repeat('é', 4000);
        $environment = str_repeat('é', 255);
        $this->browserRequest('POST', $this->resolvePath($parent), self::payload($proposal, $parent->lock_version, [
            'validation_note' => "\u{00A0}\u{200B}".$note."\u{200B}\u{00A0}",
            'environment' => '  '.$environment.'  ',
        ]), ['Idempotency-Key' => self::KEY])->assertOk()->assertJsonPath('data.validation_note', $note)->assertJsonPath('data.environment', $environment);
    }

    #[DataProvider('currentVisibilityChanges')]
    public function test_visibility_and_current_ownership_are_checked_again_before_successful_replay(string $kind, int $status): void
    {
        [$owner, $author, $parent, $proposal] = $this->fixture();
        $this->login($owner);
        $payload = self::payload($proposal, $parent->lock_version);
        $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => self::KEY])->assertOk();
        match ($kind) {
            'parent_hidden' => $parent->forceFill(['hidden_at' => now()])->save(),
            'proposal_hidden' => $proposal->forceFill(['hidden_at' => now()])->save(),
            'proposal_author_suspended' => $author->forceFill(['status' => 'suspended'])->save(),
            'proposal_author_unverified' => $author->forceFill(['email_verified_at' => null])->save(),
            'owner_suspended' => $owner->forceFill(['status' => 'suspended'])->save(),
            'owner_unverified' => $owner->forceFill(['email_verified_at' => null])->save(),
            'owner_changed' => $parent->forceFill(['author_id' => User::factory()->verified()->create()->id])->save(),
            default => throw new \InvalidArgumentException('Fixture de visibilité B19 inconnue.'),
        };
        $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => self::KEY])->assertStatus($status);
        $this->assertDatabaseCount('resolutions', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->assertDatabaseCount('content_revisions', 2);
        $this->assertDatabaseCount('notification_outbox', 1);
    }

    /** @return iterable<string, array{string, int}> */
    public static function currentVisibilityChanges(): iterable
    {
        foreach (['parent_hidden', 'proposal_hidden', 'proposal_author_suspended', 'proposal_author_unverified'] as $kind) {
            yield $kind => [$kind, 404];
        }
        foreach (['owner_suspended', 'owner_unverified', 'owner_changed'] as $kind) {
            yield $kind => [$kind, 403];
        }
    }

    public function test_replay_after_another_parent_version_or_revocation_is_not_reported_as_current_success(): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        $this->login($owner);
        $payload = self::payload($proposal, $parent->lock_version);
        $response = $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => self::KEY])->assertOk();
        $parent->refresh()->forceFill(['lock_version' => 6])->save();
        $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => self::KEY])->assertStatus(409);
        Resolution::whereKey($response->json('data.id'))->update(['revoked_at' => now()]);
        $this->browserRequest('GET', $this->activePath($parent))->assertNotFound();
        $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => self::KEY])->assertNotFound();
        $this->assertDatabaseCount('resolutions', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
    }

    public function test_active_get_preserves_legacy_null_snapshots_and_excludes_revoked_history(): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        $parent->forceFill(['state' => 'resolved'])->save();
        $proposal->forceFill(['state' => 'accepted'])->save();
        $revoked = Resolution::factory()->create(['request_id' => $parent->id, 'proposal_id' => $proposal->id, 'accepted_by' => $owner->id,
            'environment' => null, 'proposal_version' => null, 'revoked_at' => now()]);
        $active = Resolution::factory()->create(['request_id' => $parent->id, 'proposal_id' => $proposal->id, 'accepted_by' => $owner->id,
            'environment' => null, 'proposal_version' => null, 'validation_note' => 'Note legacy courte.']);
        $this->browserRequest('GET', $this->activePath($parent))->assertOk()->assertJsonPath('data.id', $active->id)
            ->assertJsonPath('data.environment', null)->assertJsonPath('data.proposal_version', null)->assertJsonPath('data.validation_note', 'Note legacy courte.')
            ->assertJsonPath('data.self_resolution', false)->assertHeader('Cache-Control', 'no-store, private');
        $active->forceFill(['revoked_at' => now()])->save();
        $this->browserRequest('GET', $this->activePath($parent))->assertNotFound();
        $this->assertDatabaseHas('resolutions', ['id' => $revoked->id]);
        $this->assertDatabaseHas('resolutions', ['id' => $active->id]);
        $this->browserRequest('GET', '/api/v1/requests/not-a-uuid/resolution')->assertNotFound();
    }

    #[DataProvider('failureTargets')]
    public function test_storage_failure_rolls_back_parent_proposal_resolution_audits_outbox_and_idempotency(string $target): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        $parentBefore = $parent->refresh()->getAttributes();
        $proposalBefore = $proposal->refresh()->getAttributes();
        $this->login($owner);
        $payload = self::payload($proposal, $parent->lock_version);
        $this->installFailureTrigger($target);
        try {
            $response = $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => self::KEY])->assertStatus(500);
            $this->assertStringNotContainsString(self::NOTE, (string) $response->getContent());
            $this->assertStringNotContainsString(self::ENVIRONMENT, (string) $response->getContent());
            $this->assertSame($parentBefore, $parent->refresh()->getAttributes());
            $this->assertSame($proposalBefore, $proposal->refresh()->getAttributes());
            $this->assertNoResolutionEffects();
        } finally {
            $this->removeFailureTrigger($target);
        }
        $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => self::KEY])->assertOk();
        $this->assertDatabaseCount('resolutions', 1);
        $this->assertDatabaseCount('content_revisions', 2);
    }

    /** @return iterable<string, array{string}> */
    public static function failureTargets(): iterable
    {
        foreach (['resolution', 'parent_audit', 'resolution_audit', 'outbox', 'idempotency'] as $target) {
            yield $target => [$target];
        }
    }

    public function test_session_csrf_idempotency_key_and_verification_are_required(): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        $payload = self::payload($proposal, $parent->lock_version);
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => self::KEY])->assertUnauthorized();
        $this->login($owner);
        $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => self::KEY], sendXsrf: false)->assertStatus(419);
        $this->browserRequest('POST', $this->resolvePath($parent), $payload)->assertUnprocessable()->assertJsonValidationErrors('Idempotency-Key', 'error.fields');
        $owner->forceFill(['email_verified_at' => null])->save();
        $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertNoResolutionEffects();
    }

    public function test_command_query_parameters_and_invalid_uuid_or_key_are_rejected_without_effect(): void
    {
        [$owner, , $parent, $proposal] = $this->fixture();
        $this->login($owner);
        $payload = self::payload($proposal, $parent->lock_version);
        $this->browserRequest('POST', $this->resolvePath($parent).'?environment=forged', $payload, ['Idempotency-Key' => self::KEY])->assertUnprocessable();
        $this->browserRequest('POST', $this->resolvePath($parent), $payload, ['Idempotency-Key' => 'invalid-key'])->assertUnprocessable()->assertJsonValidationErrors('Idempotency-Key', 'error.fields');
        $this->browserRequest('POST', '/api/v1/requests/not-a-uuid/resolve', $payload, ['Idempotency-Key' => self::KEY])->assertNotFound();
        $this->assertNoResolutionEffects();
    }

    /** @return array{User, User, HelpRequest, Proposal} */
    private function fixture(int $parentVersion = 4, int $proposalVersion = 3, bool $selfResolution = false): array
    {
        $owner = User::factory()->verified()->create();
        $author = $selfResolution ? $owner : User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'in_progress', 'lock_version' => $parentVersion]);
        $parent->technologies()->attach(Technology::factory()->create());
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $author->id, 'lock_version' => $proposalVersion]);

        return [$owner, $author, $parent, $proposal];
    }

    /** @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private static function payload(Proposal $proposal, int $parentVersion, array $changes = []): array
    {
        return array_replace(['proposal_id' => $proposal->id, 'lock_version' => $parentVersion, 'proposal_lock_version' => $proposal->lock_version,
            'environment' => self::ENVIRONMENT, 'validation_note' => self::NOTE], $changes);
    }

    /** @return array<string, string> */
    private static function proposalPayload(): array
    {
        return ['diagnosis' => 'Diagnostic fictif expliquant une cause reproductible.', 'fix' => 'Correctif fictif détaillé avec étapes et prérequis.',
            'verification' => 'Vérification déclarée sur un scénario entièrement fictif.', 'limits' => 'Limites explicites avant toute adoption du correctif.',
            'code' => "  <script>code inerte à conserver</script>\r\n"];
    }

    private function resolvePath(HelpRequest $parent): string
    {
        return '/api/v1/requests/'.$parent->id.'/resolve';
    }

    private function activePath(HelpRequest $parent): string
    {
        return '/api/v1/requests/'.$parent->id.'/resolution';
    }

    private function login(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }

    private function assertNoResolutionEffects(): void
    {
        foreach (['resolutions', 'proposal_revisions', 'content_revisions', 'notification_outbox', 'internal_notifications', 'api_idempotency'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    private function assertPrivateContentAbsentFromTechnicalStorage(): void
    {
        foreach (['content_revisions', 'notification_outbox', 'api_idempotency'] as $table) {
            $serialized = json_encode(DB::table($table)->get()->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString(self::NOTE, $serialized);
            $this->assertStringNotContainsString(self::ENVIRONMENT, $serialized);
            $this->assertStringNotContainsString('code inerte à conserver', $serialized);
        }
    }

    private function installFailureTrigger(string $target): void
    {
        DB::unprepared(<<<'SQL'
CREATE FUNCTION fixture_b19_reject_insert() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Fixture B19 : stockage refusé.' USING ERRCODE = 'P0001';
END
$$
SQL);
        $statement = match ($target) {
            'resolution' => 'CREATE TRIGGER fixture_b19_reject BEFORE INSERT ON resolutions FOR EACH ROW EXECUTE FUNCTION fixture_b19_reject_insert()',
            'parent_audit' => "CREATE TRIGGER fixture_b19_reject BEFORE INSERT ON content_revisions FOR EACH ROW WHEN (NEW.action = 'help_request.resolved') EXECUTE FUNCTION fixture_b19_reject_insert()",
            'resolution_audit' => "CREATE TRIGGER fixture_b19_reject BEFORE INSERT ON content_revisions FOR EACH ROW WHEN (NEW.action = 'resolution.accepted') EXECUTE FUNCTION fixture_b19_reject_insert()",
            'outbox' => "CREATE TRIGGER fixture_b19_reject BEFORE INSERT ON notification_outbox FOR EACH ROW WHEN (NEW.kind = 'resolution.accepted') EXECUTE FUNCTION fixture_b19_reject_insert()",
            'idempotency' => 'CREATE TRIGGER fixture_b19_reject BEFORE INSERT ON api_idempotency FOR EACH ROW EXECUTE FUNCTION fixture_b19_reject_insert()',
            default => throw new \InvalidArgumentException('Trigger de refus B19 hors des fixtures autorisées.'),
        };
        DB::unprepared($statement);
    }

    private function removeFailureTrigger(string $target): void
    {
        $table = match ($target) {
            'resolution' => 'resolutions',
            'parent_audit', 'resolution_audit' => 'content_revisions',
            'outbox' => 'notification_outbox',
            'idempotency' => 'api_idempotency',
            default => throw new \InvalidArgumentException('Trigger de refus B19 inconnu.'),
        };
        DB::statement('DROP TRIGGER fixture_b19_reject ON '.$table);
        DB::statement('DROP FUNCTION fixture_b19_reject_insert()');
    }
}

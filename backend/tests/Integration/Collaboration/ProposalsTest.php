<?php

namespace Tests\Integration\Collaboration;

use App\Enums\Identity\Role;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\ProposalRevision;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class ProposalsTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    private const string KEY = 'b1800000-1111-4444-8888-123456789abc';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_first_creation_starts_parent_once_and_replay_and_additional_proposal_preserve_its_content(): void
    {
        $parent = $this->parentFixture();
        $actor = User::factory()->verified()->create();
        $this->login($actor);
        $original = $parent->refresh()->getAttributes();
        $payload = self::payload([
            'diagnosis' => 'Diagnostic expliqué sans exécution. <script>texte</script> ![image](https://example.test/no-fetch)',
            'code' => "  <script>texte inerte</script>\r\n\t// https://example.test/never-fetch  ",
        ]);
        Http::preventStrayRequests();
        $first = $this->browserRequest('POST', $this->createPath($parent), $payload, ['Idempotency-Key' => self::KEY])
            ->assertCreated()->assertJsonPath('data.state', 'proposed')->assertJsonPath('data.lock_version', 1)
            ->assertJsonPath('data.author.id', $actor->id)->assertJsonPath('data.author.handle', $actor->handle)
            ->assertJsonPath('data.code', $payload['code'])->assertJsonPath('data.edited_at', null)
            ->assertJsonMissingPath('data.author.email')->assertJsonMissingPath('data.author.name')
            ->assertHeader('Cache-Control', 'no-store, private');
        $id = $first->json('data.id');
        $this->assertIsString($id);
        $this->browserRequest('POST', '/api/v1/requests/'.strtoupper($parent->id).'/proposals', $payload, ['Idempotency-Key' => strtoupper(self::KEY)])
            ->assertCreated()->assertExactJson($first->json());
        $this->browserRequest('POST', $this->createPath($parent), self::payload(['fix' => 'Une intention différente pour le même identifiant.']), ['Idempotency-Key' => self::KEY])
            ->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $current = $parent->refresh()->getAttributes();
        foreach (['state', 'lock_version', 'updated_at'] as $mutable) {
            unset($original[$mutable], $current[$mutable]);
        }
        $this->assertSame($original, $current);
        $this->assertSame('in_progress', $parent->state->value);
        $this->assertSame(2, $parent->lock_version);
        $this->assertDatabaseCount('proposals', 1);
        $this->assertDatabaseCount('proposal_revisions', 1);
        $this->assertDatabaseCount('content_revisions', 2);
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->assertDatabaseHas('content_revisions', ['resource_id' => $parent->id, 'actor_id' => $actor->id, 'action' => 'help_request.started']);
        $this->assertDatabaseHas('content_revisions', ['resource_id' => $id, 'actor_id' => $actor->id, 'action' => 'proposal.created']);
        $this->assertDatabaseHas('notification_outbox', ['event_id' => $id, 'recipient_id' => $parent->author_id, 'kind' => 'proposal.created', 'delivered_at' => null]);
        $this->assertDatabaseCount('internal_notifications', 0);
        $this->assertStringNotContainsString('<script>', (string) $first->json('data.diagnosis_html'));
        $this->assertStringNotContainsString('<img', (string) $first->json('data.diagnosis_html'));
        Http::assertNothingSent();

        $this->browserRequest('POST', $this->createPath($parent), self::payload(), ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $this->assertSame(2, $parent->refresh()->lock_version);
        $this->assertSame(1, DB::table('content_revisions')->where('action', 'help_request.started')->count());
        $this->assertDatabaseCount('content_revisions', 3);
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->browserRequest('GET', '/api/v1/proposals/'.$id)->assertOk()->assertExactJson($first->json())->assertHeader('Cache-Control', 'no-store, private');
        $this->browserRequest('GET', $this->createPath($parent))->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_partial_update_versions_history_and_current_replays_are_consistent(): void
    {
        $parent = $this->parentFixture('in_progress');
        $actor = User::factory()->verified()->create();
        $this->login($actor);
        $payload = self::payload(['code' => "  ancien\r\n"]);
        $id = $this->browserRequest('POST', $this->createPath($parent), $payload, ['Idempotency-Key' => self::KEY])->assertCreated()->json('data.id');
        $originalParent = $parent->refresh()->getAttributes();
        $change = ['fix' => 'Correctif clarifié avec une procédure reproductible.', 'code' => '', 'lock_version' => 1];
        $edited = $this->browserRequest('PATCH', '/api/v1/proposals/'.$id, $change, ['Idempotency-Key' => self::KEY])
            ->assertOk()->assertJsonPath('data.lock_version', 2)->assertJsonPath('data.code', '')
            ->assertJsonPath('data.diagnosis', $payload['diagnosis'])->assertJsonPath('data.verification', $payload['verification']);
        $this->assertNotNull($edited->json('data.edited_at'));
        $this->browserRequest('PATCH', '/api/v1/proposals/'.$id, $change, ['Idempotency-Key' => self::KEY])->assertOk()->assertExactJson($edited->json());
        $this->browserRequest('PATCH', '/api/v1/proposals/'.$id, $change, ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409);
        $this->browserRequest('POST', $this->createPath($parent), $payload, ['Idempotency-Key' => self::KEY])->assertStatus(409);
        $this->browserRequest('GET', '/api/v1/proposals/'.$id.'/revisions?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.proposal_version', 2)->assertJsonPath('data.0.action', 'updated')->assertJsonPath('data.0.code', '');
        $this->browserRequest('GET', '/api/v1/proposals/'.$id.'/revisions?page=2&per_page=1')->assertOk()
            ->assertJsonPath('data.0.proposal_version', 1)->assertJsonPath('data.0.action', 'created')->assertJsonPath('data.0.code', $payload['code']);
        $this->assertSame($originalParent, $parent->refresh()->getAttributes());
        $this->assertDatabaseCount('proposal_revisions', 2);
        $this->assertDatabaseCount('content_revisions', 2);
        $this->assertDatabaseCount('api_idempotency', 2);
        $this->assertDatabaseCount('notification_outbox', 1);
    }

    public function test_first_edit_of_legacy_proposal_captures_only_its_known_version(): void
    {
        $parent = $this->parentFixture('in_progress');
        $actor = User::factory()->verified()->create();
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $actor->id, 'lock_version' => 7, 'diagnosis' => 'Ancien texte court', 'code' => "  code connu\r\n"]);
        $this->login($actor);
        $this->browserRequest('PATCH', '/api/v1/proposals/'.$proposal->id, ['diagnosis' => 'Diagnostic actuel avec une cause probable expliquée.', 'lock_version' => 7], ['Idempotency-Key' => self::KEY])
            ->assertOk()->assertJsonPath('data.lock_version', 8);
        $this->assertSame([7, 8], ProposalRevision::orderBy('proposal_version')->pluck('proposal_version')->all());
        $this->assertDatabaseHas('proposal_revisions', ['proposal_id' => $proposal->id, 'proposal_version' => 7, 'action' => 'before_edit', 'diagnosis' => 'Ancien texte court', 'code' => "  code connu\r\n"]);
        $this->assertDatabaseHas('proposal_revisions', ['proposal_id' => $proposal->id, 'proposal_version' => 8, 'action' => 'updated']);
        $this->browserRequest('GET', '/api/v1/proposals/'.$proposal->id.'/revisions')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_secret_in_known_legacy_snapshot_refuses_edit_without_rewriting_or_expurgating_it(): void
    {
        $parent = $this->parentFixture('in_progress');
        $actor = User::factory()->verified()->create();
        $legacy = 'api_key='.str_repeat('a', 40);
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $actor->id, 'lock_version' => 7, 'diagnosis' => $legacy]);
        $original = $proposal->refresh()->getAttributes();
        $this->login($actor);
        $response = $this->browserRequest('PATCH', '/api/v1/proposals/'.$proposal->id, ['diagnosis' => 'Description corrigée sans aucune donnée sensible.', 'lock_version' => 7], ['Idempotency-Key' => self::KEY])
            ->assertStatus(409)->assertJsonPath('error.code', 'PROPOSAL_HISTORY_REQUIRES_REVIEW');
        $this->assertStringNotContainsString($legacy, (string) $response->getContent());
        $this->assertSame($original, $proposal->refresh()->getAttributes());
        foreach (['proposal_revisions', 'content_revisions', 'notification_outbox', 'api_idempotency'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    #[DataProvider('roles')]
    public function test_third_party_cannot_edit_even_with_privileged_role(Role $role): void
    {
        $parent = $this->parentFixture('in_progress');
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => User::factory()->verified()]);
        $this->login(User::factory()->verified()->create(['role' => $role]));
        $this->browserRequest('PATCH', '/api/v1/proposals/'.$proposal->id, ['fix' => 'Une modification faite par une autre personne.', 'lock_version' => 1], ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertSame(1, $proposal->refresh()->lock_version);
        $this->assertDatabaseCount('proposal_revisions', 0);
        $this->assertDatabaseCount('api_idempotency', 0);
    }

    /** @return iterable<string, array{Role}> */
    public static function roles(): iterable
    {
        foreach (Role::cases() as $role) {
            yield $role->value => [$role];
        }
    }

    #[DataProvider('closedProposalStates')]
    public function test_accepted_or_not_selected_proposal_is_readable_but_not_editable(string $state): void
    {
        $parent = $this->parentFixture('in_progress');
        $actor = User::factory()->verified()->create();
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $actor->id, 'state' => $state]);
        $original = $proposal->refresh()->getAttributes();
        $this->login($actor);
        $this->browserRequest('PATCH', '/api/v1/proposals/'.$proposal->id, ['fix' => 'Une réécriture après la décision du propriétaire.', 'lock_version' => 1], ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->browserRequest('GET', '/api/v1/proposals/'.$proposal->id)->assertOk()->assertJsonPath('data.state', $state);
        $this->assertSame($original, $proposal->refresh()->getAttributes());
        $this->assertDatabaseCount('proposal_revisions', 0);
    }

    /** @return iterable<string, array{string}> */
    public static function closedProposalStates(): iterable
    {
        yield 'accepted' => ['accepted'];
        yield 'not selected' => ['not_selected'];
    }

    #[DataProvider('unwritableParentStates')]
    public function test_parent_state_limits_both_creation_and_update(string $state): void
    {
        $parent = $this->parentFixture($state);
        $actor = User::findOrFail($parent->author_id);
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $actor->id]);
        $this->login($actor);
        $this->browserRequest('POST', $this->createPath($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->browserRequest('PATCH', '/api/v1/proposals/'.$proposal->id, ['fix' => 'Un correctif refusé sur une demande non modifiable.', 'lock_version' => 1], ['Idempotency-Key' => self::KEY])
            ->assertStatus($state === 'draft' ? 404 : 403);
        $this->assertSame(1, $parent->refresh()->lock_version);
        $this->assertSame(1, $proposal->refresh()->lock_version);
        $this->assertDatabaseCount('proposal_revisions', 0);
        $this->assertDatabaseCount('api_idempotency', 0);
        if ($state !== 'draft') {
            $this->browserRequest('GET', '/api/v1/proposals/'.$proposal->id)->assertOk();
        }
    }

    /** @return iterable<string, array{string}> */
    public static function unwritableParentStates(): iterable
    {
        foreach (['draft', 'resolved', 'archived'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('invisibleKinds')]
    public function test_current_visibility_filters_details_history_counts_and_replay(string $kind): void
    {
        $parent = $this->parentFixture();
        $actor = User::factory()->verified()->create();
        $this->login($actor);
        $id = $this->browserRequest('POST', $this->createPath($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertCreated()->json('data.id');
        match ($kind) {
            'parent' => $parent->forceFill(['hidden_at' => now()])->save(),
            'owner' => User::whereKey($parent->author_id)->update(['status' => 'suspended']),
            'owner_unverified' => User::whereKey($parent->author_id)->update(['email_verified_at' => null]),
            'author' => $actor->forceFill(['status' => 'suspended'])->save(),
            'proposal' => Proposal::whereKey($id)->update(['hidden_at' => now()]),
            'draft' => $parent->forceFill(['state' => 'draft'])->save(),
            default => throw new \InvalidArgumentException('Fixture de visibilité B18 inconnue.'),
        };
        $this->browserRequest('POST', $this->createPath($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertStatus($kind === 'author' ? 403 : 404);
        $this->browserCookies = [];
        $this->browserRequest('GET', '/api/v1/proposals/'.$id)->assertNotFound();
        $this->browserRequest('GET', '/api/v1/proposals/'.$id.'/revisions')->assertNotFound();
        $list = $this->browserRequest('GET', $this->createPath($parent));
        if (in_array($kind, ['parent', 'owner', 'owner_unverified', 'draft'], true)) {
            $list->assertNotFound();
        } else {
            $list->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
        }
        $this->assertDatabaseCount('proposals', 1);
        $this->assertDatabaseCount('proposal_revisions', 1);
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
    }

    /** @return iterable<string, array{string}> */
    public static function invisibleKinds(): iterable
    {
        foreach (['parent', 'owner', 'owner_unverified', 'author', 'proposal', 'draft'] as $kind) {
            yield $kind => [$kind];
        }
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidCreatePayloads')]
    public function test_invalid_or_protected_creation_input_has_no_effect(array $payload, string $field): void
    {
        $parent = $this->parentFixture();
        $this->login(User::factory()->verified()->create());
        $this->browserRequest('POST', $this->createPath($parent), $payload, ['Idempotency-Key' => self::KEY])
            ->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
        $this->assertSame('open', $parent->refresh()->state->value);
        $this->assertSame(1, $parent->lock_version);
        foreach (['proposals', 'proposal_revisions', 'content_revisions', 'notification_outbox', 'api_idempotency'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidCreatePayloads(): iterable
    {
        foreach (['diagnosis', 'fix', 'verification', 'limits'] as $field) {
            $missing = self::payload();
            unset($missing[$field]);
            yield 'missing '.$field => [$missing, $field];
        }
        foreach ([str_repeat('a', 19), str_repeat('é', 4001), str_repeat("\u{200B}", 30), 'https://example.test/only-a-link', '[Libellé explicatif assez long](https://example.test/only-a-link)', "Texte explicatif invalide\x01", ['texte'], 12] as $index => $value) {
            yield 'invalid diagnosis '.$index => [self::payload(['diagnosis' => $value]), 'diagnosis'];
        }
        yield 'oversized Unicode code' => [self::payload(['code' => str_repeat('é', 12001)]), 'code'];
        yield 'control in code' => [self::payload(['code' => "code\x01"]), 'code'];
        yield 'secret in code' => [self::payload(['code' => 'api_key='.str_repeat('a', 40)]), 'code'];
        foreach (['id' => 'b1800000-1111-4444-8888-123456789abd', 'author_id' => 'b1800000-1111-4444-8888-123456789abd', 'request_id' => 'b1800000-1111-4444-8888-123456789abd', 'state' => 'accepted', 'lock_version' => 8, 'edited_at' => null, 'hidden_at' => null, 'unexpected' => true] as $field => $value) {
            yield 'server field '.$field => [self::payload([$field => $value]), $field];
        }
    }

    #[DataProvider('codeValues')]
    public function test_code_preserves_validated_bytes_null_empty_and_unicode_limit(?string $code): void
    {
        $parent = $this->parentFixture();
        $this->login(User::factory()->verified()->create());
        Http::preventStrayRequests();
        $response = $this->browserRequest('POST', $this->createPath($parent), self::payload(['code' => $code, 'limits' => str_repeat('é', 4000)]), ['Idempotency-Key' => self::KEY])
            ->assertCreated()->assertJsonPath('data.code', $code);
        $this->assertSame($code, Proposal::whereKey($response->json('data.id'))->firstOrFail()->code);
        $this->assertSame($code, ProposalRevision::sole()->code);
        Http::assertNothingSent();
    }

    /** @return iterable<string, array{string|null}> */
    public static function codeValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'indentation and inert HTML' => [" \t<script>contenu</script>\r\n  https://example.test/no-fetch  "];
        yield '12000 Unicode characters' => [str_repeat('é', 12000)];
    }

    public function test_useful_description_length_is_checked_after_unicode_edge_trim(): void
    {
        $parent = $this->parentFixture();
        $this->login(User::factory()->verified()->create());
        $text = str_repeat('é', 4000);
        $this->browserRequest('POST', $this->createPath($parent), self::payload(['diagnosis' => "\u{200B}\u{00A0}".$text."\u{00A0}\u{200B}"]), ['Idempotency-Key' => self::KEY])
            ->assertCreated()->assertJsonPath('data.diagnosis', $text);
        $this->assertSame($text, Proposal::sole()->diagnosis);
    }

    /** @param array<string, mixed> $change */
    #[DataProvider('invalidUpdatePayloads')]
    public function test_partial_update_requires_strict_integer_version_and_mutable_content(array $change, string $field): void
    {
        $parent = $this->parentFixture('in_progress');
        $actor = User::factory()->verified()->create();
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $actor->id]);
        $original = $proposal->refresh()->getAttributes();
        $this->login($actor);
        $this->browserRequest('PATCH', '/api/v1/proposals/'.$proposal->id, $change, ['Idempotency-Key' => self::KEY])
            ->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
        $this->assertSame($original, $proposal->refresh()->getAttributes());
        foreach (['proposal_revisions', 'content_revisions', 'notification_outbox', 'api_idempotency'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidUpdatePayloads(): iterable
    {
        foreach (['1', 1.5, true, null, 0, 2147483647, ['1']] as $index => $version) {
            yield 'invalid version '.$index => [['fix' => 'Un correctif documenté mais une version invalide.', 'lock_version' => $version], 'lock_version'];
        }
        yield 'missing version' => [['fix' => 'Un correctif documenté sans version courante.'], 'lock_version'];
        yield 'no change' => [['lock_version' => 1], 'changes'];
        yield 'protected state' => [['fix' => 'Un correctif documenté avec état injecté.', 'lock_version' => 1, 'state' => 'accepted'], 'state'];
    }

    #[DataProvider('failureTables')]
    public function test_server_side_storage_failure_rolls_back_creation_and_parent_transition_then_allows_retry(string $table, ?string $auditAction): void
    {
        $parent = $this->parentFixture();
        $this->login(User::factory()->verified()->create());
        $original = $parent->refresh()->getAttributes();
        $this->installFailureTrigger($table, $auditAction);
        try {
            $response = $this->browserRequest('POST', $this->createPath($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertStatus(500);
            $this->assertStringNotContainsString('Diagnostic détaillé avec une cause probable expliquée.', (string) $response->getContent());
            $this->assertSame($original, $parent->refresh()->getAttributes());
            foreach (['proposals', 'proposal_revisions', 'content_revisions', 'notification_outbox', 'api_idempotency'] as $storage) {
                $this->assertDatabaseCount($storage, 0);
            }
        } finally {
            $this->removeFailureTrigger($table);
        }
        $this->browserRequest('POST', $this->createPath($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertCreated();
        $this->assertSame(2, $parent->refresh()->lock_version);
        $this->assertDatabaseCount('content_revisions', 2);
    }

    /** @return iterable<string, array{string, string|null}> */
    public static function failureTables(): iterable
    {
        yield 'revision' => ['proposal_revisions', null];
        yield 'parent audit' => ['content_revisions', 'help_request.started'];
        yield 'proposal audit after parent audit' => ['content_revisions', 'proposal.created'];
        yield 'outbox' => ['notification_outbox', null];
        yield 'idempotency' => ['api_idempotency', null];
    }

    public function test_audit_failure_rolls_back_partial_update_and_history(): void
    {
        $parent = $this->parentFixture('in_progress');
        $actor = User::factory()->verified()->create();
        $this->login($actor);
        $id = $this->browserRequest('POST', $this->createPath($parent), self::payload(), ['Idempotency-Key' => self::KEY])->assertCreated()->json('data.id');
        $proposal = Proposal::whereKey($id)->firstOrFail();
        $original = $proposal->refresh()->getAttributes();
        $this->installFailureTrigger('content_revisions', 'proposal.updated');
        try {
            $this->browserRequest('PATCH', '/api/v1/proposals/'.$id, ['fix' => 'Une modification valide qui doit être annulée.', 'lock_version' => 1], ['Idempotency-Key' => self::KEY])->assertStatus(500);
            $this->assertSame($original, $proposal->refresh()->getAttributes());
            $this->assertDatabaseCount('proposal_revisions', 1);
            $this->assertDatabaseCount('content_revisions', 1);
            $this->assertDatabaseCount('notification_outbox', 1);
            $this->assertDatabaseCount('api_idempotency', 1);
        } finally {
            $this->removeFailureTrigger('content_revisions');
        }
    }

    public function test_pagination_filters_unavailable_authors_before_total_and_rejects_unknown_parameters(): void
    {
        $parent = $this->parentFixture('in_progress');
        $at = now()->utc();
        foreach ([1, 2, 3] as $number) {
            Proposal::factory()->create(['id' => 'b1800000-1111-4444-8888-123456789ab'.$number, 'request_id' => $parent->id, 'author_id' => User::factory()->verified(), 'created_at' => $at]);
        }
        Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => User::factory()->verified(), 'hidden_at' => now()]);
        Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => User::factory()->verified()->create(['status' => 'suspended'])->id]);
        Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => User::factory()->create()]);
        $this->browserRequest('GET', $this->createPath($parent).'?per_page=2')->assertOk()->assertJsonPath('meta.total', 3)->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 'b1800000-1111-4444-8888-123456789ab1')->assertJsonPath('data.1.id', 'b1800000-1111-4444-8888-123456789ab2');
        $this->browserRequest('GET', $this->createPath($parent).'?page=2&per_page=2')->assertOk()->assertJsonPath('meta.total', 3)->assertJsonCount(1, 'data');
        $this->browserRequest('GET', $this->createPath($parent).'?per_page=51')->assertUnprocessable();
        $this->browserRequest('GET', $this->createPath($parent).'?state=accepted')->assertUnprocessable();
        $this->browserRequest('GET', '/api/v1/proposals/not-a-uuid')->assertNotFound();
    }

    public function test_session_csrf_key_verification_and_active_account_are_required(): void
    {
        $parent = $this->parentFixture();
        $path = $this->createPath($parent);
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', $path, self::payload(), ['Idempotency-Key' => self::KEY])->assertUnauthorized();
        $actor = User::factory()->verified()->create();
        $this->login($actor);
        $this->browserRequest('POST', $path, self::payload(), ['Idempotency-Key' => self::KEY], sendXsrf: false)->assertStatus(419);
        $this->browserRequest('POST', $path, self::payload())->assertUnprocessable()->assertJsonValidationErrors('Idempotency-Key', 'error.fields');
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->login(User::factory()->create());
        $this->browserRequest('POST', $path, self::payload(), ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->login($actor);
        $actor->forceFill(['status' => 'suspended'])->save();
        $this->browserRequest('POST', $path, self::payload(), ['Idempotency-Key' => self::KEY])->assertForbidden();
        $this->assertDatabaseCount('proposals', 0);
    }

    /** @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private static function payload(array $changes = []): array
    {
        return array_replace([
            'diagnosis' => 'Diagnostic détaillé avec une cause probable expliquée.',
            'fix' => 'Correctif documenté avec les étapes nécessaires.',
            'verification' => 'Vérification reproductible après application du correctif.',
            'limits' => 'Limites connues et portée du correctif proposé.',
        ], $changes);
    }

    private function parentFixture(string $state = 'open'): HelpRequest
    {
        $parent = HelpRequest::factory()->for(User::factory()->verified(), 'author')->create(['state' => $state]);
        $parent->technologies()->attach(Technology::factory()->create());

        return $parent;
    }

    private function createPath(HelpRequest $parent): string
    {
        return '/api/v1/requests/'.$parent->id.'/proposals';
    }

    private function login(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }

    private function installFailureTrigger(string $table, ?string $auditAction): void
    {
        $this->assertContains($table, ['proposal_revisions', 'content_revisions', 'notification_outbox', 'api_idempotency']);
        $this->assertContains($auditAction, [null, 'help_request.started', 'proposal.created', 'proposal.updated']);
        DB::unprepared(<<<'SQL'
CREATE FUNCTION fixture_b18_reject_insert() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Fixture B18 : stockage refusé.' USING ERRCODE = 'P0001';
END
$$
SQL);
        $statement = match ([$table, $auditAction]) {
            ['proposal_revisions', null] => 'CREATE TRIGGER fixture_b18_reject BEFORE INSERT ON proposal_revisions FOR EACH ROW EXECUTE FUNCTION fixture_b18_reject_insert()',
            ['content_revisions', 'help_request.started'] => "CREATE TRIGGER fixture_b18_reject BEFORE INSERT ON content_revisions FOR EACH ROW WHEN (NEW.action = 'help_request.started') EXECUTE FUNCTION fixture_b18_reject_insert()",
            ['content_revisions', 'proposal.created'] => "CREATE TRIGGER fixture_b18_reject BEFORE INSERT ON content_revisions FOR EACH ROW WHEN (NEW.action = 'proposal.created') EXECUTE FUNCTION fixture_b18_reject_insert()",
            ['content_revisions', 'proposal.updated'] => "CREATE TRIGGER fixture_b18_reject BEFORE INSERT ON content_revisions FOR EACH ROW WHEN (NEW.action = 'proposal.updated') EXECUTE FUNCTION fixture_b18_reject_insert()",
            ['notification_outbox', null] => 'CREATE TRIGGER fixture_b18_reject BEFORE INSERT ON notification_outbox FOR EACH ROW EXECUTE FUNCTION fixture_b18_reject_insert()',
            ['api_idempotency', null] => 'CREATE TRIGGER fixture_b18_reject BEFORE INSERT ON api_idempotency FOR EACH ROW EXECUTE FUNCTION fixture_b18_reject_insert()',
            default => throw new \InvalidArgumentException('Trigger de refus B18 hors des fixtures autorisées.'),
        };
        DB::unprepared($statement);
    }

    private function removeFailureTrigger(string $table): void
    {
        DB::statement('DROP TRIGGER fixture_b18_reject ON '.$table);
        DB::statement('DROP FUNCTION fixture_b18_reject_insert()');
    }
}

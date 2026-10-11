<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Data\Capsules\CapsuleDraftData;
use App\Data\Capsules\VersionDraftData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\Capsules\CapsuleSourceKind;
use App\Enums\Collaboration\ProposalState;
use App\Enums\HelpRequests\HelpRequestState;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\User;
use App\Services\Capsules\CreateCapsuleDraftService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class CapsuleSourceAuthorReadinessTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    /** @return iterable<string, array{string}> */
    public static function revocations(): iterable
    {
        yield 'suspension' => ['status'];
        yield 'verification retirée' => ['email_verified_at'];
    }

    #[DataProvider('revocations')]
    public function test_accepted_proposer_cannot_create_from_an_inaccessible_source(string $field): void
    {
        [$author, $actor, $source] = $this->source();
        $this->revoke($author, $field);
        $this->login($actor);
        $this->browserRequest('POST', '/api/v1/capsules', $this->payload($source), $this->key())->assertForbidden();
        $this->assertDatabaseCount('capsules', 0);
        $this->assertDatabaseCount('api_idempotency', 0);
    }

    #[DataProvider('revocations')]
    public function test_source_author_access_is_rechecked_on_replay(string $field): void
    {
        [$author, $actor, $source] = $this->source();
        $this->login($actor);
        $key = $this->key();
        $this->browserRequest('POST', '/api/v1/capsules', $this->payload($source), $key)->assertCreated();
        $this->revoke($author, $field);
        $this->browserRequest('POST', '/api/v1/capsules', $this->payload($source), $key)->assertForbidden();
        $this->assertDatabaseCount('capsules', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->assertDatabaseCount('content_revisions', 1);
    }

    #[DataProvider('revocations')]
    public function test_service_reloads_the_source_author_instead_of_a_stale_model(string $field): void
    {
        [$author, $actor, $source] = $this->source();
        $source->load('author');
        $this->revoke($author, $field);
        $cachedAuthor = $source->author;
        $this->assertInstanceOf(User::class, $cachedAuthor);
        $this->assertTrue($cachedAuthor->hasVerifiedEmail());
        $data = new CapsuleDraftData('source-auteur-verifie', CapsuleSourceKind::HelpRequest, $source->id, null,
            new VersionDraftData('1.0.0', 'Diagnostic documenté pour un exemple fictif.', 'Limites explicitement déclarées.', []));
        try {
            app(CreateCapsuleDraftService::class)->handle($actor, $data, new IdempotencyKey((string) Str::uuid()));
            $this->fail('Un auteur source inaccessible doit interdire la création.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('capsules', 0);
            $this->assertDatabaseCount('api_idempotency', 0);
        }
    }

    /** @return array{User, User, HelpRequest} */
    private function source(): array
    {
        $author = User::factory()->verified()->create();
        $actor = User::factory()->verified()->create();
        $source = HelpRequest::factory()->create(['author_id' => $author->id, 'state' => HelpRequestState::Resolved]);
        $proposal = Proposal::factory()->create(['request_id' => $source->id, 'author_id' => $actor->id, 'state' => ProposalState::Accepted]);
        Resolution::factory()->create(['request_id' => $source->id, 'proposal_id' => $proposal->id, 'accepted_by' => $author->id]);

        return [$author, $actor, $source];
    }

    private function revoke(User $author, string $field): void
    {
        User::whereKey($author->id)->update([$field => $field === 'status' ? 'suspended' : null]);
    }

    private function login(User $actor): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $actor->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }

    /** @return array<string, string> */
    private function key(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }

    /** @return array{slug:string, source:array<string,string>, version:array<string,string>} */
    private function payload(HelpRequest $source): array
    {
        return ['slug' => 'source-auteur-verifie', 'source' => ['kind' => 'help_request', 'help_request_id' => $source->id],
            'version' => ['version_label' => '1.0.0', 'body' => 'Diagnostic documenté pour un exemple fictif.', 'limits' => 'Limites explicitement déclarées.']];
    }
}

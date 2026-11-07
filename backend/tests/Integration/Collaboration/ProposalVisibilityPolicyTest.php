<?php

namespace Tests\Integration\Collaboration;

use App\Data\Common\PageData;
use App\Enums\Collaboration\ProposalState;
use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\AccountStatus;
use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\ProposalRevision;
use App\Models\User;
use App\Policies\ProposalPolicy;
use App\Queries\Collaboration\ProposalQuery;
use App\Queries\Collaboration\VisibleProposalsQuery;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;

final class ProposalVisibilityPolicyTest extends PostgresTestCase
{
    use RefreshDatabase;

    #[DataProvider('unavailableResources')]
    public function test_visibility_is_applied_before_pagination_total_detail_and_history(string $reason): void
    {
        $owner = User::factory()->verified()->create();
        $contributor = User::factory()->verified()->create();
        $parent = $this->requestFixture($owner);
        $first = $this->proposalFixture($parent, $contributor, ['created_at' => '2026-10-08 10:00:00+00']);
        $second = $this->proposalFixture($parent, $contributor, ['created_at' => '2026-10-08 11:00:00+00']);
        $unavailableOwner = User::factory()->verified()->create();
        $unavailableContributor = User::factory()->verified()->create();
        $unavailableParent = str_starts_with($reason, 'parent_') ? $this->requestFixture($unavailableOwner) : $parent;
        // This row sorts first if visibility is mistakenly applied after pagination.
        $unavailable = $this->proposalFixture($unavailableParent, $unavailableContributor, ['created_at' => '2026-10-08 09:00:00+00']);
        $revision = new ProposalRevision;
        $revision->forceFill([
            'proposal_id' => $unavailable->id, 'proposal_version' => 1, 'action' => 'created',
            ...$unavailable->only(['diagnosis', 'fix', 'verification', 'limits']),
            'code' => null, 'occurred_at' => '2026-10-08 09:00:00+00',
        ])->save();

        match ($reason) {
            'parent_draft' => $unavailableParent->forceFill(['state' => HelpRequestState::Draft])->save(),
            'parent_hidden' => $unavailableParent->forceFill(['hidden_at' => now()])->save(),
            'parent_author_suspended' => $unavailableOwner->forceFill(['status' => AccountStatus::Suspended])->save(),
            'parent_author_unverified' => $unavailableOwner->forceFill(['email_verified_at' => null])->save(),
            'proposal_hidden' => $unavailable->forceFill(['hidden_at' => now()])->save(),
            'contributor_suspended' => $unavailableContributor->forceFill(['status' => AccountStatus::Suspended])->save(),
            'contributor_unverified' => $unavailableContributor->forceFill(['email_verified_at' => null])->save(),
            default => throw new LogicException('Fixture de visibilité B18 inconnue.'),
        };

        $visible = app(VisibleProposalsQuery::class);
        $queries = app(ProposalQuery::class);
        foreach ([null, $owner] as $reader) {
            $page = $visible->forActor($reader)->orderBy('created_at')->orderBy('id')->paginate(1, ['*'], 'page', 1);
            $this->assertSame(2, $page->total());
            $this->assertSame([$first->id], $page->getCollection()->pluck('id')->all());
            $next = $visible->forActor($reader)->orderBy('created_at')->orderBy('id')->paginate(1, ['*'], 'page', 2);
            $this->assertSame(2, $next->total());
            $this->assertSame([$second->id], $next->getCollection()->pluck('id')->all());
            $scoped = $queries->page($reader, strtoupper($parent->id), new PageData(1, 1));
            $this->assertSame(2, $scoped->total());
            $this->assertSame([$first->id], $scoped->getCollection()->pluck('id')->all());
            $this->assertSame($first->id, $visible->get($reader, strtoupper($first->id))->id);
            $this->assertProposalNotFound(fn () => $visible->get($reader, $unavailable->id));
            $this->assertProposalNotFound(fn () => $queries->revisions($reader, $unavailable->id, new PageData));
        }
        $this->assertDatabaseHas('proposal_revisions', ['id' => $revision->id, 'proposal_id' => $unavailable->id]);
        if ($reason === 'parent_draft') {
            $this->assertSame(0, $queries->page($unavailableOwner, $unavailableParent->id, new PageData)->total());
            $this->assertProposalNotFound(fn () => $visible->get($unavailableOwner, $unavailable->id));
        }
    }

    /** @return iterable<string, array{string}> */
    public static function unavailableResources(): iterable
    {
        foreach (['parent_draft', 'parent_hidden', 'parent_author_suspended', 'parent_author_unverified', 'proposal_hidden', 'contributor_suspended', 'contributor_unverified'] as $reason) {
            yield $reason => [$reason];
        }
    }

    public function test_only_the_verified_proposal_author_can_edit_without_an_admin_bypass(): void
    {
        $owner = User::factory()->verified()->create();
        $author = User::factory()->verified()->create();
        $other = User::factory()->verified()->create();
        $admin = User::factory()->verified()->admin()->create();
        $parent = $this->requestFixture($owner);
        $proposal = $this->proposalFixture($parent, $author);
        $policy = app(ProposalPolicy::class);

        $this->assertTrue($policy->create($author, $parent));
        $this->assertTrue($policy->update($author, $proposal));
        foreach ([$owner, $other, $admin] as $actor) {
            $this->assertFalse($policy->update($actor, $proposal));
        }
    }

    #[DataProvider('parentStates')]
    public function test_creation_and_edit_require_an_open_or_in_progress_parent(HelpRequestState $state, bool $allowed): void
    {
        $owner = User::factory()->verified()->create();
        $author = User::factory()->verified()->create();
        $parent = $this->requestFixture($owner, $state);
        $proposal = $this->proposalFixture($parent, $author);
        $policy = app(ProposalPolicy::class);

        $this->assertSame($allowed, $policy->create($author, $parent));
        $this->assertSame($allowed, $policy->update($author, $proposal));
    }

    /** @return iterable<string, array{HelpRequestState, bool}> */
    public static function parentStates(): iterable
    {
        foreach (HelpRequestState::cases() as $state) {
            yield $state->value => [$state, in_array($state, [HelpRequestState::Open, HelpRequestState::InProgress], true)];
        }
    }

    #[DataProvider('proposalStates')]
    public function test_accepted_and_not_selected_proposals_cannot_be_edited(ProposalState $state, bool $allowed): void
    {
        $owner = User::factory()->verified()->create();
        $author = User::factory()->verified()->create();
        $proposal = $this->proposalFixture($this->requestFixture($owner), $author, ['state' => $state]);

        $this->assertSame($allowed, app(ProposalPolicy::class)->update($author, $proposal));
    }

    /** @return iterable<string, array{ProposalState, bool}> */
    public static function proposalStates(): iterable
    {
        foreach (ProposalState::cases() as $state) {
            yield $state->value => [$state, $state === ProposalState::Proposed];
        }
    }

    #[DataProvider('unavailableWriteTargets')]
    public function test_current_visibility_and_account_status_prevent_edits(string $reason): void
    {
        $owner = User::factory()->verified()->create();
        $author = User::factory()->verified()->create();
        $parent = $this->requestFixture($owner);
        $proposal = $this->proposalFixture($parent, $author);
        match ($reason) {
            'parent_hidden' => $parent->forceFill(['hidden_at' => now()])->save(),
            'parent_author_suspended' => $owner->forceFill(['status' => AccountStatus::Suspended])->save(),
            'parent_author_unverified' => $owner->forceFill(['email_verified_at' => null])->save(),
            'proposal_hidden' => $proposal->forceFill(['hidden_at' => now()])->save(),
            'contributor_suspended' => $author->forceFill(['status' => AccountStatus::Suspended])->save(),
            'contributor_unverified' => $author->forceFill(['email_verified_at' => null])->save(),
            default => throw new LogicException('Fixture de droits B18 inconnue.'),
        };
        $policy = app(ProposalPolicy::class);

        $this->assertFalse($policy->update($author, $proposal));
        $this->assertSame($reason === 'proposal_hidden', $policy->create($author, $parent));
    }

    /** @return iterable<string, array{string}> */
    public static function unavailableWriteTargets(): iterable
    {
        foreach (['parent_hidden', 'parent_author_suspended', 'parent_author_unverified', 'proposal_hidden', 'contributor_suspended', 'contributor_unverified'] as $reason) {
            yield $reason => [$reason];
        }
    }

    private function requestFixture(User $owner, HelpRequestState $state = HelpRequestState::Open): HelpRequest
    {
        return HelpRequest::factory()->for($owner, 'author')->create(['state' => $state]);
    }

    /** @param array<string, mixed> $attributes */
    private function proposalFixture(HelpRequest $parent, User $author, array $attributes = []): Proposal
    {
        return Proposal::factory()->for($parent, 'request')->for($author, 'author')->create($attributes);
    }

    /** @param Closure(): mixed $query */
    private function assertProposalNotFound(Closure $query): void
    {
        try {
            $query();
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(Proposal::class, $exception->getModel());

            return;
        }
        $this->fail('La proposition et son historique doivent être inaccessibles.');
    }
}

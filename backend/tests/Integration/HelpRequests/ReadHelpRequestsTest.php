<?php

namespace Tests\Integration\HelpRequests;

use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\HelpRequest;
use App\Models\Technology;
use App\Models\User;
use App\Policies\HelpRequestPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class ReadHelpRequestsTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_public_reads_never_expose_drafts_hidden_or_ineligible_authors_in_results_or_totals(): void
    {
        $author = User::factory()->verified()->create();
        $visible = HelpRequest::factory()->for($author, 'author')->create(['state' => 'open']);
        $hidden = HelpRequest::factory()->for($author, 'author')->create(['state' => 'open', 'hidden_at' => now()]);
        $draft = HelpRequest::factory()->for($author, 'author')->create();
        $unverified = HelpRequest::factory()->for(User::factory(), 'author')->create(['state' => 'open']);
        $suspended = HelpRequest::factory()->for(User::factory()->verified()->state(['status' => AccountStatus::Suspended]), 'author')->create(['state' => 'open']);
        $technology = Technology::factory()->create(['name' => 'Technologie commune', 'slug' => 'commune']);
        foreach ([$visible, $hidden, $draft, $unverified, $suspended] as $item) {
            $item->technologies()->attach($technology);
        }
        foreach (['', '?q=Blocage', '?q=commune', '?technology='.$technology->id, '?state=open', '?per_page=1'] as $filter) {
            $this->browserRequest('GET', '/api/v1/requests'.$filter)->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $visible->id)->assertJsonPath('meta.total', 1)->assertHeader('Cache-Control', 'no-store, private');
        }
        foreach ([$hidden, $draft, $unverified, $suspended] as $item) {
            $this->browserRequest('GET', '/api/v1/requests/'.$item->id)->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        }
        $this->browserRequest('GET', '/api/v1/requests?state=draft')->assertOk()->assertJsonPath('meta.total', 0);
        $this->browserRequest('GET', '/api/v1/requests/'.$visible->id)->assertOk()->assertJsonPath('data.id', $visible->id);
    }

    public function test_own_drafts_require_an_explicit_private_scope_and_are_visible_only_to_the_owner(): void
    {
        $owner = User::factory()->verified()->create();
        $draft = HelpRequest::factory()->for($owner, 'author')->create();
        $public = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'open']);
        HelpRequest::factory()->for($owner, 'author')->create(['hidden_at' => now()]);
        HelpRequest::factory()->for(User::factory()->verified(), 'author')->create(['state' => 'open']);
        $this->browserRequest('GET', '/api/v1/requests?scope=mine')->assertUnauthorized();
        $this->login($owner);
        $this->browserRequest('GET', '/api/v1/requests?scope=mine')->assertOk()->assertJsonPath('meta.total', 2);
        $this->browserRequest('GET', '/api/v1/requests?scope=mine&state=draft')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $draft->id);
        $this->browserRequest('GET', '/api/v1/requests?scope=mine&state=open')->assertOk()->assertJsonPath('data.0.id', $public->id);
        $this->browserRequest('GET', '/api/v1/requests/'.$draft->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->browserRequest('GET', '/api/v1/requests?state=draft')->assertOk()->assertJsonPath('meta.total', 0);
    }

    #[DataProvider('roles')]
    public function test_roles_never_grant_access_to_someone_elses_draft(Role $role): void
    {
        $draft = HelpRequest::factory()->for(User::factory()->verified(), 'author')->create();
        $this->login(User::factory()->verified()->state(['role' => $role])->create());
        $this->browserRequest('GET', '/api/v1/requests/'.$draft->id)->assertNotFound();
        $this->browserRequest('GET', '/api/v1/requests?scope=mine')->assertOk()->assertJsonPath('meta.total', 0);
        $this->browserRequest('GET', '/api/v1/requests?q=Blocage')->assertOk()->assertJsonPath('meta.total', 0);
    }

    /** @return iterable<string, array{Role}> */
    public static function roles(): iterable
    {
        foreach (Role::cases() as $role) {
            yield $role->value => [$role];
        }
    }

    public function test_unverified_reader_can_read_publications_but_cannot_read_private_drafts(): void
    {
        $reader = User::factory()->create();
        $draft = HelpRequest::factory()->for($reader, 'author')->create();
        $public = HelpRequest::factory()->for(User::factory()->verified(), 'author')->create(['state' => 'open']);
        $this->login($reader);
        $this->browserRequest('GET', '/api/v1/requests')->assertOk()->assertJsonPath('meta.total', 1);
        $this->browserRequest('GET', '/api/v1/requests/'.$public->id)->assertOk();
        $this->browserRequest('GET', '/api/v1/requests/'.$draft->id)->assertNotFound();
        $this->browserRequest('GET', '/api/v1/requests?scope=mine')->assertForbidden();
    }

    public function test_visibility_is_rechecked_after_hiding_suspension_and_account_change(): void
    {
        $owner = User::factory()->verified()->create();
        $item = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'open']);
        $this->browserRequest('GET', '/api/v1/requests/'.$item->id)->assertOk();
        $item->forceFill(['hidden_at' => now()])->save();
        $this->browserRequest('GET', '/api/v1/requests/'.$item->id)->assertNotFound();
        $this->browserRequest('GET', '/api/v1/requests')->assertOk()->assertJsonPath('meta.total', 0);
        $item->forceFill(['hidden_at' => null])->save();
        $owner->forceFill(['status' => AccountStatus::Suspended])->save();
        $this->browserRequest('GET', '/api/v1/requests/'.$item->id)->assertNotFound();
        $this->browserRequest('GET', '/api/v1/requests?q=Blocage')->assertOk()->assertJsonPath('meta.total', 0);
        $owner->forceFill(['status' => AccountStatus::Active])->save();
        $item->forceFill(['state' => HelpRequestState::Draft])->save();
        $this->login($owner);
        $this->browserRequest('GET', '/api/v1/requests/'.$item->id)->assertOk();
        $this->browserRequest('POST', '/logout')->assertNoContent();
        $this->login(User::factory()->verified()->create());
        $this->browserRequest('GET', '/api/v1/requests/'.$item->id)->assertNotFound();
        $this->browserRequest('GET', '/api/v1/requests?scope=mine')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_suspended_session_cannot_read_own_drafts(): void
    {
        $owner = User::factory()->verified()->create();
        $draft = HelpRequest::factory()->for($owner, 'author')->create();
        $this->login($owner);
        $owner->forceFill(['status' => AccountStatus::Suspended])->save();
        $this->browserRequest('GET', '/api/v1/requests?scope=mine')->assertForbidden();
        $this->browserRequest('GET', '/api/v1/requests/'.$draft->id)->assertNotFound();
    }

    public function test_filters_are_intersected_and_search_excludes_private_or_unrelated_content(): void
    {
        $owner = User::factory()->verified()->create(['email' => 'private-search@example.test']);
        $tech = Technology::factory()->create(['name' => 'Nébuleuse Framework', 'slug' => 'unique-tech']);
        $item = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'resolved', 'title' => 'Littéral 100% foo_bar!',
            'goal' => 'Un BUT documentaire', 'expected' => 'Attendu-spécifique', 'observed' => 'Constat-spécifique',
            'attempts' => 'Piste-spécifique', 'environment' => 'Environnement-spécifique', 'code' => 'code-marker-private', 'reproduction_url' => 'https://example.test/url-marker']);
        $item->technologies()->attach($tech);
        // Une correspondance technologique privée ne doit pas élargir le groupe SQL OR.
        HelpRequest::factory()->for($owner, 'author')->create(['hidden_at' => now(), 'state' => 'open'])->technologies()->attach($tech);
        HelpRequest::factory()->for($owner, 'author')->create(['state' => 'open', 'title' => 'Littéral 1000 fooXbar']);
        foreach (['100%', 'foo_bar!', 'BUT', 'attendu-spécifique', 'constat-spécifique', 'piste-spécifique', 'environnement-spécifique', 'nébuleuse', 'unique-tech'] as $needle) {
            $this->browserRequest('GET', '/api/v1/requests?'.http_build_query(['q' => $needle, 'state' => 'resolved', 'technology' => strtoupper($tech->id)]))
                ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $item->id);
        }
        foreach (['code-marker-private', 'url-marker', 'private-search@example.test', "' OR 1=1 --"] as $needle) {
            $this->browserRequest('GET', '/api/v1/requests?'.http_build_query(['q' => $needle]))->assertOk()->assertJsonPath('meta.total', 0);
        }
        $this->browserRequest('GET', '/api/v1/requests?q=unique-tech&state=open')->assertOk()->assertJsonPath('meta.total', 0);
        $this->browserRequest('GET', '/api/v1/requests?technology='.Str::uuid())->assertOk()->assertJsonPath('meta.total', 0);
        $this->browserRequest('GET', '/api/v1/requests?q=')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_wildcards_are_literal_without_any_other_filter(): void
    {
        $owner = User::factory()->verified()->create();
        $literal = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'open', 'title' => '100% foo_bar!', 'environment' => 'Linux']);
        HelpRequest::factory()->for($owner, 'author')->create(['state' => 'open', 'title' => '1000 fooXbar', 'environment' => 'Linux']);
        foreach (['%', '_', '!'] as $needle) {
            $this->browserRequest('GET', '/api/v1/requests?'.http_build_query(['q' => $needle]))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $literal->id);
        }
    }

    #[DataProvider('states')]
    public function test_published_states_have_consistent_policy_list_and_detail(HelpRequestState $state): void
    {
        $item = HelpRequest::factory()->for(User::factory()->verified(), 'author')->create(['state' => $state]);
        $allowed = app(HelpRequestPolicy::class)->view(null, $item);
        $this->assertSame($state !== HelpRequestState::Draft, $allowed);
        $this->browserRequest('GET', '/api/v1/requests?state='.$state->value)->assertOk()->assertJsonPath('meta.total', $allowed ? 1 : 0);
        $this->browserRequest('GET', '/api/v1/requests/'.$item->id)->assertStatus($allowed ? 200 : 404);
    }

    /** @return iterable<string, array{HelpRequestState}> */
    public static function states(): iterable
    {
        foreach (HelpRequestState::cases() as $state) {
            yield $state->value => [$state];
        }
    }

    public function test_sort_and_pagination_are_stable_and_counts_apply_after_visibility(): void
    {
        $owner = User::factory()->verified()->create();
        $items = HelpRequest::factory()->count(23)->for($owner, 'author')->create(['state' => 'open', 'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-01 10:00:00']);
        HelpRequest::factory()->count(4)->for($owner, 'author')->create();
        $ids = $items->pluck('id')->sort()->values()->all();
        $first = $this->browserRequest('GET', '/api/v1/requests')->assertOk()->assertJsonCount(20, 'data')
            ->assertJsonPath('meta', ['current_page' => 1, 'from' => 1, 'last_page' => 2, 'per_page' => 20, 'to' => 20, 'total' => 23]);
        $second = $this->browserRequest('GET', '/api/v1/requests?page=2')->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame($ids, array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->browserRequest('GET', '/api/v1/requests?page=3')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 23)->assertJsonPath('meta.from', null);
        $this->browserRequest('GET', '/api/v1/requests?per_page=50')->assertOk()->assertJsonCount(23, 'data');
        $new = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'open', 'created_at' => '2026-10-03 12:00:00', 'updated_at' => '2026-10-03 12:00:00']);
        $this->browserRequest('GET', '/api/v1/requests?sort=newest')->assertOk()->assertJsonPath('data.0.id', $new->id);
        $this->browserRequest('GET', '/api/v1/requests?sort=oldest')->assertOk()->assertJsonPath('data.0.id', $ids[0]);
        $edited = $items->last();
        $this->assertNotNull($edited);
        $edited->forceFill(['updated_at' => '2026-10-04 10:00:00'])->save();
        $this->browserRequest('GET', '/api/v1/requests?sort=updated')->assertOk()->assertJsonPath('data.0.id', $edited->id);
    }

    public function test_response_is_explicit_and_eager_loading_does_not_grow_with_page_size(): void
    {
        $owners = User::factory()->verified()->count(25)->create();
        $tech = Technology::factory()->create();
        foreach ($owners as $owner) {
            HelpRequest::factory()->for($owner, 'author')->create(['state' => 'open', 'code' => '<script>inerte</script>'])->technologies()->attach($tech, ['version_label' => '17']);
        }
        Model::preventLazyLoading();
        DB::enableQueryLog();
        try {
            $counts = [];
            foreach ([5, 25] as $size) {
                DB::flushQueryLog();
                $response = $this->browserRequest('GET', '/api/v1/requests?per_page='.$size)->assertOk()->assertJsonCount($size, 'data');
                $counts[] = count(array_filter(DB::getQueryLog(), fn (array $query): bool => str_starts_with($query['query'], 'select') && ! str_contains($query['query'], '"sessions"')));
                $schema = Yaml::parseFile(base_path('../docs/api/openapi/community.yaml'))['components']['schemas']['HelpRequest'];
                $this->assertEqualsCanonicalizing($schema['required'], array_keys($response->json('data.0')));
                $this->assertSame(['id', 'handle'], array_keys($response->json('data.0.author')));
                $response->assertJsonPath('data.0.code', '<script>inerte</script>')->assertJsonPath('data.0.technologies.0.version_label', '17');
            }
            $this->assertSame([4, 4], $counts, 'count + demandes + auteurs + technologies, sans N+1');
        } finally {
            DB::disableQueryLog();
            Model::preventLazyLoading(false);
        }
        $first = HelpRequest::query()->firstOrFail();
        $this->browserRequest('GET', '/api/v1/requests/'.$first->id)->assertOk()->assertJsonPath('data.code', '<script>inerte</script>')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_creation_and_reads_share_the_same_resource_and_do_not_execute_links(): void
    {
        $this->login(User::factory()->verified()->create());
        Http::preventStrayRequests();
        $created = $this->browserRequest('POST', '/api/v1/requests', ['mode' => 'draft', 'help_intent' => 'ask_question', 'title' => 'Question à préparer', 'reproduction_url' => 'https://example.test/never-fetch'], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $this->browserRequest('GET', '/api/v1/requests/'.$created->json('data.id'))->assertOk()->assertExactJson($created->json());
        $this->browserRequest('GET', '/api/v1/requests?scope=mine')->assertOk()->assertJsonPath('data.0', $created->json('data'));
        $this->assertDatabaseCount('content_revisions', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
        Http::assertNothingSent();
    }

    public function test_missing_and_malformed_identifiers_are_indistinguishable_from_inaccessible_content(): void
    {
        foreach ([(string) Str::uuid(), 'not-a-uuid', "' OR 1=1"] as $id) {
            $this->browserRequest('GET', '/api/v1/requests/'.rawurlencode($id))->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        }
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_or_unknown_filters_are_rejected(string $query, string $field): void
    {
        $this->browserRequest('GET', '/api/v1/requests?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidFilters(): iterable
    {
        foreach (['sort=title' => 'sort', 'sort[]=newest' => 'sort', 'state=hidden' => 'state', 'state=' => 'state',
            'scope=all' => 'scope', 'technology=nope' => 'technology', 'q[]=test' => 'q', 'q='.str_repeat('a', 201) => 'q',
            'per_page=51' => 'per_page', 'per_page=0' => 'per_page', 'per_page=1.5' => 'per_page', 'page=-1' => 'page',
            'page=2147483648' => 'page', 'page_size=20' => 'page_size', 'author_id=' => 'author_id', 'hidden_at=' => 'hidden_at'] as $query => $field) {
            yield $field.'-'.$query => [$query, $field];
        }
    }

    private function login(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}

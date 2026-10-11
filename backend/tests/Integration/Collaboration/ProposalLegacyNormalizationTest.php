<?php

namespace Tests\Integration\Collaboration;

use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class ProposalLegacyNormalizationTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_partial_edit_normalizes_legacy_descriptions_but_preserves_the_known_original_snapshot(): void
    {
        $owner = User::factory()->verified()->create();
        $actor = User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'in_progress']);
        $original = " \u{00A0}".str_repeat('é', 4000)."\u{202F} ";
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $actor->id,
            'diagnosis' => $original, 'code' => "  ancien code\r\n", 'lock_version' => 7]);
        $this->login($actor);
        $this->browserRequest('PATCH', '/api/v1/proposals/'.$proposal->id,
            ['code' => null, 'lock_version' => 7], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('data.lock_version', 8)->assertJsonPath('data.diagnosis', str_repeat('é', 4000))->assertJsonPath('data.code', null);
        $this->assertDatabaseHas('proposal_revisions', ['proposal_id' => $proposal->id, 'proposal_version' => 7, 'action' => 'before_edit', 'diagnosis' => $original, 'code' => "  ancien code\r\n"]);
        $this->assertDatabaseHas('proposal_revisions', ['proposal_id' => $proposal->id, 'proposal_version' => 8, 'action' => 'updated', 'diagnosis' => str_repeat('é', 4000), 'code' => null]);
        $this->assertSame(1, $parent->refresh()->lock_version);
    }

    public function test_forbidden_edge_controls_are_rejected_before_middleware_normalization(): void
    {
        $owner = User::factory()->verified()->create();
        $actor = User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'open']);
        $this->login($actor);
        foreach (["\x00", "\x0B", "\x0C"] as $control) {
            $body = array_fill_keys(['diagnosis', 'fix', 'verification', 'limits'], 'Description entièrement fictive.');
            $body['diagnosis'] = $control.$body['diagnosis'].$control;
            $this->browserRequest('POST', '/api/v1/requests/'.$parent->id.'/proposals', $body, ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        }
        foreach (['proposals', 'proposal_revisions', 'content_revisions', 'notification_outbox', 'api_idempotency'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame('open', $parent->refresh()->state->value);
        $this->assertSame(1, $parent->lock_version);
    }

    private function login(User $actor): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $actor->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}

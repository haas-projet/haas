<?php

namespace Tests\Integration\Collaboration;

use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\Resolution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class ResolutionReplayRegressionTest extends PostgresTestCase
{
    use RefreshDatabase, SpaHttpRequests;

    public function test_a_replaced_active_resolution_is_a_conflict_instead_of_an_internal_failure(): void
    {
        $this->configureSpa('database');
        $owner = User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'open']);
        $proposal = Proposal::factory()->for($parent, 'request')->for(User::factory()->verified(), 'author')->create();
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $owner->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
        $path = '/api/v1/requests/'.$parent->id.'/resolve';
        $body = ['proposal_id' => $proposal->id, 'lock_version' => 1, 'proposal_lock_version' => 1,
            'environment' => 'Contexte de démonstration fictif', 'validation_note' => 'La proposition a aidé dans ce contexte entièrement fictif.'];
        $headers = ['Idempotency-Key' => 'b1900000-4444-4444-8888-123456789abc'];
        $first = $this->browserRequest('POST', $path, $body, $headers)->assertOk();
        $old = Resolution::whereKey($first->json('data.id'))->firstOrFail();
        // Simulate a consistent historical replacement, not the future B20 command.
        $replacement = $old->replicate();
        $old->revoked_at = now()->utc()->toImmutable();
        $old->save();
        $replacement->save();
        $this->assertNotSame($old->id, $replacement->id);
        $this->browserRequest('POST', $path, $body, $headers)->assertStatus(409)->assertJsonPath('error.code', 'RESOURCE_CONFLICT');
        $this->assertDatabaseCount('resolutions', 2);
        $this->assertDatabaseCount('api_idempotency', 1);
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('content_revisions', 2);
        $this->assertSame($replacement->id, Resolution::whereNull('revoked_at')->sole()->id);
    }
}

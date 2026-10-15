<?php

declare(strict_types=1);

namespace Tests\Integration\VerificationCases;

use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\Role;
use App\Enums\VerificationCases\VerificationCaseState;
use App\Models\HelpRequest;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Models\VerificationCases\VerificationCaseRevision;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class VerificationCaseReviewHttpTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_anonymous_cannot_review(): void
    {
        $case = $this->submittedCase();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('POST', '/api/v1/admin/verification-cases/'.$case->id.'/review', [
            'lock_version' => $case->lock_version,
            'decision' => 'reviewed',
        ], $this->keyHeader());
        $response->assertUnauthorized();
    }

    public function test_simple_member_is_refused(): void
    {
        $case = $this->submittedCase();
        $member = User::factory()->verified()->create();
        $this->loginAs($member);
        $response = $this->browserRequest('POST', '/api/v1/admin/verification-cases/'.$case->id.'/review', [
            'lock_version' => $case->lock_version,
            'decision' => 'reviewed',
        ], $this->keyHeader());
        $response->assertStatus(403);
    }

    public function test_moderator_distinct_from_author_marks_the_case_as_reviewed(): void
    {
        $case = $this->submittedCase();
        $reviewer = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->loginAs($reviewer);
        $response = $this->browserRequest('POST', '/api/v1/admin/verification-cases/'.$case->id.'/review', [
            'lock_version' => $case->lock_version,
            'decision' => 'reviewed',
        ], $this->keyHeader());
        $response->assertOk();
        $response->assertJsonPath('data.state', VerificationCaseState::Reviewed->value);
    }

    public function test_admin_author_cannot_self_review(): void
    {
        $admin = User::factory()->verified()->create(['role' => Role::Admin]);
        $parent = $this->visibleHelpRequest();
        $case = VerificationCase::factory()->create([
            'author_id' => $admin->id,
            'help_request_id' => $parent->id,
            'state' => VerificationCaseState::Submitted,
            'current_revision' => 1,
        ]);
        VerificationCaseRevision::factory()->create([
            'case_id' => $case->id,
            'revision_no' => 1,
            'created_at' => now()->subMinute(),
            'submitted_at' => now(),
        ]);
        $this->loginAs($admin);
        $response = $this->browserRequest('POST', '/api/v1/admin/verification-cases/'.$case->id.'/review', [
            'lock_version' => $case->lock_version,
            'decision' => 'reviewed',
        ], $this->keyHeader());
        $response->assertStatus(403);
    }

    public function test_declined_without_reason_is_422(): void
    {
        $case = $this->submittedCase();
        $reviewer = User::factory()->verified()->create(['role' => Role::Admin]);
        $this->loginAs($reviewer);
        $response = $this->browserRequest('POST', '/api/v1/admin/verification-cases/'.$case->id.'/review', [
            'lock_version' => $case->lock_version,
            'decision' => 'declined',
        ], $this->keyHeader());
        $response->assertStatus(422);
        $this->assertArrayHasKey('reason', $response->json('error.fields') ?? []);
    }

    public function test_review_on_a_non_submitted_case_is_409(): void
    {
        $case = $this->submittedCase(VerificationCaseState::Draft, false);
        $reviewer = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->loginAs($reviewer);
        $response = $this->browserRequest('POST', '/api/v1/admin/verification-cases/'.$case->id.'/review', [
            'lock_version' => $case->lock_version,
            'decision' => 'reviewed',
        ], $this->keyHeader());
        $response->assertStatus(409);
    }

    public function test_replay_with_same_key_and_payload_returns_the_same_result(): void
    {
        $case = $this->submittedCase();
        $reviewer = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->loginAs($reviewer);
        $key = ['Idempotency-Key' => (string) Str::uuid()];
        $first = $this->browserRequest('POST', '/api/v1/admin/verification-cases/'.$case->id.'/review', [
            'lock_version' => $case->lock_version,
            'decision' => 'reviewed',
        ], $key);
        $second = $this->browserRequest('POST', '/api/v1/admin/verification-cases/'.$case->id.'/review', [
            'lock_version' => $case->lock_version,
            'decision' => 'reviewed',
        ], $key);
        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('data.lock_version'), $second->json('data.lock_version'));
    }

    public function test_no_public_route_writes_case_scenario_links_or_sets_integrated(): void
    {
        $forbidden = ['case_scenario_links', 'integrated'];
        $publicWrites = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1/')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS', 'GET']) as $method) {
                $publicWrites[] = $method.' /'.$uri;
            }
        }
        foreach ($publicWrites as $write) {
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $write, 'Aucune route publique ne doit écrire '.$needle.'. Trouvée : '.$write);
            }
        }
    }

    private function submittedCase(VerificationCaseState $state = VerificationCaseState::Submitted, bool $withSubmittedRevision = true): VerificationCase
    {
        $parent = $this->visibleHelpRequest();
        $author = User::factory()->verified()->create();
        $case = VerificationCase::factory()->create([
            'author_id' => $author->id,
            'help_request_id' => $parent->id,
            'state' => $state,
            'current_revision' => $state === VerificationCaseState::Draft ? 0 : 1,
        ]);
        VerificationCaseRevision::factory()->create([
            'case_id' => $case->id,
            'revision_no' => 1,
            'created_at' => now()->subMinute(),
            'submitted_at' => $withSubmittedRevision ? now() : null,
        ]);

        return $case;
    }

    private function visibleHelpRequest(): HelpRequest
    {
        $author = User::factory()->verified()->create();

        return HelpRequest::factory()->create([
            'author_id' => $author->id,
            'state' => HelpRequestState::Open,
        ]);
    }

    private function loginAs(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }

    /** @return array<string, string> */
    private function keyHeader(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Data\Capsules\ReviewCommandData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Exceptions\Idempotency\IdempotencyStorageFailed;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\RequestChangesOnCapsuleVersionService;
use App\Services\Capsules\SubmitCapsuleVersionForReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class CapsuleReviewReadinessTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_submission_requires_an_intention_and_version_and_rejects_unknown_fields(): void
    {
        [$owner, $capsule, $version] = $this->draft();
        $this->login($owner);
        $this->browserRequest('POST', $this->url($capsule, $version))->assertUnprocessable();
        $this->browserRequest('POST', $this->url($capsule, $version), ['lock_version' => 1, 'reviewer_id' => null], $this->key())->assertUnprocessable();
        $this->assertDatabaseCount('api_idempotency', 0);
        $this->assertSame(CapsuleVersionState::Draft, $version->refresh()->state);
    }

    public function test_submission_replay_is_exact_and_a_stale_version_has_a_real_conflict(): void
    {
        [$owner, $capsule, $version] = $this->draft();
        $this->login($owner);
        $this->browserRequest('POST', $this->url($capsule, $version), ['lock_version' => 42], $this->key())->assertConflict();
        $key = $this->key();
        $this->browserRequest('POST', $this->url($capsule, $version), ['lock_version' => 1], $key)->assertOk()->assertJsonPath('data.lock_version', 2);
        $this->browserRequest('POST', $this->url($capsule, $version), ['lock_version' => 1], $key)->assertOk()->assertJsonPath('data.lock_version', 2);
        $this->browserRequest('POST', $this->url($capsule, $version), ['lock_version' => 2], $key)->assertConflict();
        $this->assertDatabaseCount('content_revisions', 1);
        $this->assertDatabaseCount('api_idempotency', 1);
    }

    public function test_review_notes_allow_lines_but_refuse_likely_secrets_and_controls(): void
    {
        [, $capsule, $version] = $this->draft(CapsuleVersionState::InReview);
        $reviewer = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->login($reviewer);
        $note = "Clarifiez les limites.\nAjoutez une procédure reproductible.";
        $this->browserRequest('POST', $this->url($capsule, $version, true), ['lock_version' => 1, 'note' => 'Fictif api_key=example-only-value'], $this->key())->assertUnprocessable();
        $this->browserRequest('POST', $this->url($capsule, $version, true), ['lock_version' => 1, 'note' => "Note de revue fictive avec contrôle\0interdit."], $this->key())->assertUnprocessable();
        $this->browserRequest('POST', $this->url($capsule, $version, true), ['lock_version' => 1, 'note' => $note], $this->key())->assertCreated()->assertJsonPath('data.note', $note);
        $this->assertDatabaseCount('capsule_version_reviews', 1);
    }

    public function test_review_replay_rechecks_the_current_role(): void
    {
        [, $capsule, $version] = $this->draft(CapsuleVersionState::InReview);
        $reviewer = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->login($reviewer);
        $key = $this->key();
        $payload = ['lock_version' => 1, 'note' => 'Complétez les limites et les étapes manquantes.'];
        $first = $this->browserRequest('POST', $this->url($capsule, $version, true), $payload, $key)->assertCreated();
        $this->browserRequest('POST', $this->url($capsule, $version, true), $payload, $key)->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        User::whereKey($reviewer->id)->update(['role' => Role::Member->value]);
        $this->browserRequest('POST', $this->url($capsule, $version, true), $payload, $key)->assertForbidden();
        $this->assertDatabaseCount('capsule_version_reviews', 1);
        $this->assertDatabaseCount('content_revisions', 1);
    }

    public function test_changes_edit_and_resubmission_keep_the_review_history_and_invalidate_old_replay(): void
    {
        [$owner, $capsule, $version] = $this->draft();
        $this->login($owner);
        $key = $this->key();
        $this->browserRequest('POST', $this->url($capsule, $version), ['lock_version' => 1], $key)->assertOk();
        $reviewer = User::factory()->verified()->create(['role' => Role::Moderator]);
        $this->login($reviewer);
        $this->browserRequest('POST', $this->url($capsule, $version, true), ['lock_version' => 2, 'note' => 'Complétez les limites avant la prochaine revue.'], $this->key())->assertCreated();
        $this->login($owner);
        $this->browserRequest('PATCH', "/api/v1/capsules/{$capsule->id}/versions/{$version->id}", ['lock_version' => 3, 'limits' => 'Limites complétées après revue.'])->assertOk();
        $this->browserRequest('POST', $this->url($capsule, $version), ['lock_version' => 1], $key)->assertConflict();
        $this->browserRequest('POST', $this->url($capsule, $version), ['lock_version' => 4], $this->key())->assertOk()->assertJsonPath('data.lock_version', 5);
        $this->assertNull($version->refresh()->reviewer_id);
        $this->assertNull($version->published_at);
        $this->assertDatabaseCount('capsule_version_reviews', 1);
        $this->assertDatabaseCount('content_revisions', 4);
    }

    public function test_a_stale_actor_object_cannot_submit_after_suspension(): void
    {
        [$owner, $capsule, $version] = $this->draft();
        User::whereKey($owner->id)->update(['status' => AccountStatus::Suspended->value]);
        try {
            app(SubmitCapsuleVersionForReviewService::class)->handle($owner, $capsule, $version, new ReviewCommandData(1), new IdempotencyKey((string) Str::uuid()));
            $this->fail('Le compte suspendu ne peut soumettre avec un ancien objet acteur.');
        } catch (AuthorizationException) {
            $this->assertSame(CapsuleVersionState::Draft, $version->refresh()->state);
            $this->assertDatabaseCount('api_idempotency', 0);
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function auditCommands(): iterable
    {
        yield 'soumission' => [false];
        yield 'corrections' => [true];
    }

    #[DataProvider('auditCommands')]
    public function test_a_real_audit_failure_rolls_back_the_transition_and_intention(bool $review): void
    {
        $state = $review ? CapsuleVersionState::InReview : CapsuleVersionState::Draft;
        [$owner, $capsule, $version] = $this->draft($state);
        $actor = $review ? User::factory()->verified()->create(['role' => Role::Moderator]) : $owner;
        DB::unprepared("CREATE FUNCTION reject_capsule_audit_fixture() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''échec fictif audit''; END'; CREATE TRIGGER reject_capsule_audit_fixture BEFORE INSERT ON content_revisions FOR EACH ROW EXECUTE FUNCTION reject_capsule_audit_fixture()");
        try {
            $key = new IdempotencyKey((string) Str::uuid());
            if ($review) {
                app(RequestChangesOnCapsuleVersionService::class)->handle($actor, $capsule, $version, new ReviewCommandData(1, 'Complétez les limites et les étapes de ce scénario.'), $key);
            } else {
                app(SubmitCapsuleVersionForReviewService::class)->handle($actor, $capsule, $version, new ReviewCommandData(1), $key);
            }
            $this->fail('La panne SQL fictive doit remonter.');
        } catch (IdempotencyStorageFailed) {
            $this->assertSame($state, $version->refresh()->state);
            $this->assertSame(1, $version->lock_version);
            $this->assertNull($version->reviewer_id);
            $this->assertDatabaseCount('capsule_version_reviews', 0);
            $this->assertDatabaseCount('content_revisions', 0);
            $this->assertDatabaseCount('api_idempotency', 0);
        } finally {
            DB::unprepared('DROP TRIGGER reject_capsule_audit_fixture ON content_revisions; DROP FUNCTION reject_capsule_audit_fixture()');
        }
    }

    /** @return array{User, Capsule, CapsuleVersion} */
    private function draft(CapsuleVersionState $state = CapsuleVersionState::Draft): array
    {
        $owner = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id]);
        $version = CapsuleVersion::factory()->create(['capsule_id' => $capsule->id, 'state' => $state, 'limits' => 'Limites explicites de ce scénario.']);

        return [$owner, $capsule, $version];
    }

    private function login(User $actor): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $actor->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }

    private function url(Capsule $capsule, CapsuleVersion $version, bool $review = false): string
    {
        return '/api/v1/'.($review ? 'admin/' : '')."capsules/{$capsule->id}/versions/{$version->id}/".($review ? 'request-changes' : 'submit-review');
    }

    /** @return array<string, string> */
    private function key(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }
}

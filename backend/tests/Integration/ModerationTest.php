<?php

namespace Tests\Integration;

use App\Data\Identity\UpdateProfileData;
use App\Data\Moderation\ReportData;
use App\Data\Moderation\ReportDecisionData;
use App\Exceptions\ModerationRejected;
use App\Exceptions\ModerationStorageFailed;
use App\Models\Profile;
use App\Models\User;
use App\Services\Identity\UpdateProfileService;
use App\Services\Moderation\CreateReportService;
use App\Services\Moderation\DecideReportService;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class ModerationTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    private const string DETAIL = 'Signalement fictif sans recopier de secret exposé.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_report_is_private_encrypted_unique_and_requires_a_visible_authorized_target(): void
    {
        $owner = User::factory()->verified()->create();
        Profile::factory()->for($owner)->create();
        $reporter = User::factory()->verified()->create();
        $body = ['resource_type' => 'profile', 'resource_id' => $owner->id, 'category' => 'secret_exposed', 'detail' => self::DETAIL];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/api/v1/reports', $body)->assertUnauthorized();
        $this->login($reporter);
        $this->browserRequest('POST', '/api/v1/reports', $body, sendXsrf: false)->assertStatus(419);
        $this->browserRequest('POST', '/api/v1/reports', $body + ['reporter_id' => $owner->id])->assertUnprocessable();
        $response = $this->browserRequest('POST', '/api/v1/reports', $body)->assertCreated();
        $this->assertEqualsCanonicalizing(['id', 'resource_type', 'resource_id', 'status', 'lock_version', 'created_at'], array_keys($response->json('data')));
        $id = $response->json('data.id');
        $this->browserRequest('POST', '/api/v1/reports', $body)->assertConflict();
        $this->browserRequest('GET', '/api/v1/admin/reports')->assertForbidden();
        $this->browserRequest('GET', '/api/v1/admin/reports/'.$id)->assertForbidden();
        $this->assertDatabaseCount('reports', 1);
        $row = DB::table('reports')->sole();
        $this->assertSame(self::DETAIL, Crypt::decryptString($row->detail_encrypted));
        $this->assertStringNotContainsString(self::DETAIL, json_encode($row, JSON_THROW_ON_ERROR));
        Profile::whereKey($owner->id)->update(['hidden_at' => now()]);
        $this->browserRequest('POST', '/api/v1/reports', $body)->assertNotFound();
    }

    public function test_moderator_filters_reviews_hides_profile_and_notifies_only_after_commit(): void
    {
        $owner = User::factory()->verified()->create();
        $reporter = User::factory()->verified()->create();
        $moderator = User::factory()->verified()->moderator()->create();
        app(UpdateProfileService::class)->update($owner, new UpdateProfileData(0, ['bio' => 'Ancien texte fictif à retirer'], null));
        $report = app(CreateReportService::class)->create($reporter, new ReportData($owner->id, 'secret_exposed', self::DETAIL));
        $this->login($moderator);
        $this->browserRequest('GET', '/api/v1/admin/reports?status=new&category=secret_exposed&from=2020-01-01')->assertOk()->assertJsonPath('meta.total', 1);
        $this->browserRequest('GET', '/api/v1/admin/reports?category=abuse')->assertOk()->assertJsonPath('meta.total', 0);
        $this->browserRequest('GET', '/api/v1/admin/reports/'.$report->id)->assertOk()->assertJsonPath('data.context.lock_version', 1)->assertJsonPath('data.detail', self::DETAIL);
        $path = '/api/v1/admin/reports/'.$report->id.'/decisions';
        $this->browserRequest('POST', $path, ['lock_version' => 0, 'action' => 'review', 'reason' => self::DETAIL])->assertOk()->assertJsonPath('data.status', 'in_review');
        $this->browserRequest('POST', $path, ['lock_version' => 1, 'action' => 'hide', 'profile_lock_version' => 0, 'reason' => self::DETAIL])->assertConflict();
        $this->browserRequest('POST', $path, ['lock_version' => 1, 'action' => 'hide', 'profile_lock_version' => 1, 'reason' => self::DETAIL])->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->browserRequest('GET', '/api/v1/members/'.$owner->handle)->assertNotFound();
        $this->browserCookies = [];
        $this->browserRequest('GET', '/api/v1/members/'.$owner->handle)->assertNotFound();
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseCount('internal_notifications', 0);
        $this->assertDatabaseHas('content_revisions', ['resource_type' => 'profile', 'revision' => 1, 'metadata' => '{}']);
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $this->login($owner);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('data.0.target_path', '/me/profile')->assertDontSee('Ancien texte');
        $this->browserRequest('GET', '/api/v1/me/profile')->assertOk()->assertJsonPath('data.hidden', true)->assertJsonPath('data.lock_version', 2);
        $this->browserRequest('PATCH', '/api/v1/me/profile', ['lock_version' => 2, 'bio' => 'Texte corrigé'])->assertOk()->assertJsonPath('data.hidden', true);
        $this->browserRequest('GET', '/api/v1/members/'.$owner->handle)->assertNotFound();
    }

    public function test_terminal_decisions_do_not_automatically_hide_and_cannot_be_repeated(): void
    {
        $moderator = User::factory()->verified()->moderator()->create();
        $reporter = User::factory()->verified()->create();
        foreach (['dismiss', 'request_correction'] as $action) {
            $owner = User::factory()->verified()->create();
            Profile::factory()->for($owner)->create();
            $report = app(CreateReportService::class)->create($reporter, new ReportData($owner->id, 'other', self::DETAIL));
            $data = new ReportDecisionData(0, $action, self::DETAIL);
            $result = app(DecideReportService::class)->decide($moderator, $report->id, $data);
            $this->assertSame($action === 'dismiss' ? 'dismissed' : 'resolved', $result->status);
            $this->assertNull($owner->profile?->hidden_at);
            try {
                app(DecideReportService::class)->decide($moderator, $report->id, $data);
                $this->fail('Une décision terminale ne doit pas être répétée.');
            } catch (ModerationRejected) {
                $this->assertSame(1, DB::table('report_decisions')->where('report_id', $report->id)->count());
            }
        }
        $this->assertDatabaseCount('notification_outbox', 2);
    }

    public function test_failure_rolls_back_hide_decision_history_and_notification(): void
    {
        $owner = User::factory()->verified()->create();
        $moderator = User::factory()->verified()->moderator()->create();
        Profile::factory()->for($owner)->create();
        $report = app(CreateReportService::class)->create($owner, new ReportData($owner->id, 'other', self::DETAIL));
        DB::statement('ALTER TABLE report_decisions ADD CONSTRAINT b31_failure CHECK (false)');
        try {
            app(DecideReportService::class)->decide($moderator, $report->id, new ReportDecisionData(0, 'hide', self::DETAIL, 0));
            $this->fail('Échec de stockage attendu.');
        } catch (ModerationStorageFailed $exception) {
            $this->assertNull($exception->getPrevious());
            $this->assertDatabaseHas('profiles', ['user_id' => $owner->id, 'hidden_at' => null, 'lock_version' => 0]);
            $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'new', 'lock_version' => 0]);
            $this->assertDatabaseCount('notification_outbox', 0);
        } finally {
            DB::statement('ALTER TABLE report_decisions DROP CONSTRAINT b31_failure');
        }
    }

    public function test_report_quota_is_per_member_and_never_triggers_an_automatic_sanction(): void
    {
        $reporter = User::factory()->verified()->create();
        $targets = User::factory()->verified()->count(6)->create();
        foreach ($targets as $index => $owner) {
            Profile::factory()->for($owner)->create();
            try {
                app(CreateReportService::class)->create($reporter, new ReportData($owner->id, 'other', self::DETAIL));
                $this->assertLessThan(5, $index);
            } catch (ModerationRejected) {
                $this->assertSame(5, $index);
            }
        }
        $this->assertDatabaseCount('reports', 5);
        $this->assertSame(0, Profile::whereNotNull('hidden_at')->count());
        $this->assertDatabaseCount('report_decisions', 0);
    }

    private function login(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}

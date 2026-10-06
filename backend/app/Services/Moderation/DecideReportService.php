<?php

namespace App\Services\Moderation;

use App\Data\Moderation\ReportDecisionData;
use App\Data\Notifications\NotificationEvent;
use App\Exceptions\ModerationRejected;
use App\Exceptions\ModerationStorageFailed;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class DecideReportService
{
    public function __construct(private readonly AuditWriter $audit, private readonly NotificationOutbox $outbox) {}

    public function decide(User $actor, string $reportId, ReportDecisionData $data): Report
    {
        try {
            return DB::transaction(function () use ($actor, $reportId, $data): Report {
                Gate::forUser(User::findOrFail($actor->id))->authorize('moderate', User::class);
                $reference = Report::findOrFail($reportId);
                $users = User::whereIn('id', [$actor->id, $reference->resource_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $current = $users->get($actor->id) ?? throw new AuthorizationException;
                Gate::forUser($current)->authorize('moderate', User::class);
                $profile = Profile::whereKey($reference->resource_id)->lockForUpdate()->firstOrFail();
                $report = Report::whereKey($reportId)->lockForUpdate()->firstOrFail();
                if ($report->resource_type !== 'profile' || $report->resource_id !== $profile->user_id || $report->lock_version !== $data->version
                    || ! in_array($report->status, ['new', 'in_review'], true) || ($data->action === 'review' && $report->status !== 'new')) {
                    throw new ModerationRejected;
                }
                if ($data->action === 'hide') {
                    if ($profile->lock_version !== $data->profileVersion || $profile->hidden_at !== null) {
                        throw new ModerationRejected;
                    }
                    $profile->hidden_at = now()->utc();
                    $profile->lock_version++;
                    $profile->save();
                    $this->audit->redactProfileHistory($current, $profile);
                }
                $report->status = match ($data->action) {
                    'review' => 'in_review', 'dismiss' => 'dismissed', default => 'resolved'
                };
                $report->lock_version++;
                $report->save();
                $decisionId = (string) Str::uuid();
                DB::table('report_decisions')->insert([
                    'id' => $decisionId, 'report_id' => $report->id, 'actor_id' => $current->id, 'action' => $data->action,
                    'reason_encrypted' => Crypt::encryptString($data->reason), 'report_version' => $report->lock_version, 'created_at' => now()->utc(),
                ]);
                DB::table('content_revisions')->insert([
                    'id' => (string) Str::uuid(), 'actor_id' => $current->id, 'resource_type' => 'report', 'resource_id' => $report->id,
                    'revision' => $report->lock_version + 1, 'action' => 'report.'.$data->action,
                    'metadata' => json_encode(['decision_id' => $decisionId], JSON_THROW_ON_ERROR), 'occurred_at' => now()->utc(),
                ]);
                if (in_array($data->action, ['hide', 'request_correction', 'dismiss'], true)) {
                    $this->outbox->record(new NotificationEvent($decisionId, $profile->user_id, 'profile.moderated'));
                }

                return $report;
            });
        } catch (QueryException) {
            throw new ModerationStorageFailed('La décision de modération n’a pas été enregistrée.');
        }
    }
}

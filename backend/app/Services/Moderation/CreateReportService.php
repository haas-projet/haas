<?php

namespace App\Services\Moderation;

use App\Data\Moderation\ReportData;
use App\Exceptions\ModerationRejected;
use App\Exceptions\ModerationStorageFailed;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class CreateReportService
{
    public function create(User $actor, ReportData $data): Report
    {
        try {
            return DB::transaction(function () use ($actor, $data): Report {
                $users = User::whereIn('id', [$actor->id, $data->resourceId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $current = $users->get($actor->id) ?? throw new AuthorizationException;
                $profile = Profile::whereKey($data->resourceId)->whereNull('hidden_at')->lockForUpdate()->firstOrFail();
                $profile->setRelation('user', $users->get($data->resourceId));
                Gate::forUser($current)->authorize('report', $profile);
                $own = Report::where('reporter_id', $current->id);
                if ((clone $own)->where('resource_type', 'profile')->where('resource_id', $profile->user_id)->whereIn('status', ['new', 'in_review'])->exists()
                    || (clone $own)->where('created_at', '>=', now()->subHour())->count() >= 5
                    || (clone $own)->where('created_at', '>=', now()->subDay())->count() >= 20) {
                    throw new ModerationRejected;
                }
                $report = new Report;
                $report->id = (string) Str::uuid();
                $report->reporter_id = $current->id;
                $report->resource_type = 'profile';
                $report->resource_id = $profile->user_id;
                $report->category = $data->category;
                $report->detail_encrypted = Crypt::encryptString($data->detail);
                $report->status = 'new';
                $report->lock_version = 0;
                $report->save();
                DB::table('content_revisions')->insert([
                    'id' => (string) Str::uuid(), 'actor_id' => $current->id, 'resource_type' => 'report', 'resource_id' => $report->id,
                    'revision' => 1, 'action' => 'report.created', 'metadata' => '{}', 'occurred_at' => now()->utc(),
                ]);

                return $report;
            });
        } catch (QueryException) {
            throw new ModerationStorageFailed('Le signalement n’a pas été enregistré.');
        }
    }
}

<?php

namespace App\Queries\Moderation;

use App\Data\Moderation\ReportFilterData;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ReportQuery
{
    /** @return LengthAwarePaginator<int, Report> */
    public function page(User $actor, ReportFilterData $filter): LengthAwarePaginator
    {
        Gate::forUser(User::findOrFail($actor->id))->authorize('moderate', User::class);
        $query = Report::query();
        if ($filter->status !== null) {
            $query->where('status', $filter->status);
        }
        if ($filter->category !== null) {
            $query->where('category', $filter->category);
        }
        if ($filter->from !== null) {
            $query->whereDate('created_at', '>=', $filter->from);
        }
        if ($filter->to !== null) {
            $query->whereDate('created_at', '<=', $filter->to);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($filter->page->perPage, ['*'], 'page', $filter->page->page);
    }

    public function detail(User $actor, string $id): Report
    {
        Gate::forUser(User::findOrFail($actor->id))->authorize('moderate', User::class);
        $report = Report::findOrFail($id);
        $profile = $report->resource_type === 'profile' ? Profile::find($report->resource_id) : null;
        $report->setAttribute('context', $profile === null ? null : ['bio' => $profile->bio, 'country' => $profile->country,
            'github_url' => $profile->github_url, 'hidden' => $profile->hidden_at !== null, 'lock_version' => $profile->lock_version]);

        return $report;
    }
}

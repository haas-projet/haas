<?php

namespace App\Queries\HelpRequests;

use App\Models\RequestReopening;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class VisibleRequestReopeningsQuery
{
    public function __construct(private readonly ResolutionHistoryQuery $history) {}

    /** @return Builder<RequestReopening> */
    public function forActor(?User $actor): Builder
    {
        return RequestReopening::whereIn('resolution_id', $this->history->forActor($actor)->select('resolutions.id'))
            ->whereHas('request', fn (Builder $parent) => $parent->whereColumn('help_requests.author_id', 'request_reopenings.reopened_by'))
            ->whereHas('resolution', fn (Builder $resolution) => $resolution->whereNotNull('revoked_at'));
    }
}

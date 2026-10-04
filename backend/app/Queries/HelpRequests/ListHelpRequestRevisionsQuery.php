<?php

namespace App\Queries\HelpRequests;

use App\Data\Common\PageData;
use App\Models\HelpRequestRevision;
use App\Models\User;
use App\Support\Identity\MemberAccess;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListHelpRequestRevisionsQuery
{
    public function __construct(private readonly FindVisibleHelpRequestQuery $visible, private readonly MemberAccess $access) {}

    /** @return LengthAwarePaginator<int, HelpRequestRevision> */
    public function get(?User $actor, string $id, PageData $page): LengthAwarePaginator
    {
        $request = $this->visible->get($actor, $id);
        $query = HelpRequestRevision::where('request_id', $request->id);
        if (! $this->access->owns($actor, $request->author_id)) {
            $query->where('is_public', true);
        }

        return $query->orderByDesc('request_version')->paginate($page->perPage, ['*'], 'page', $page->page);
    }
}

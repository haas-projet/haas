<?php

namespace App\Queries\Identity;

use App\Data\Common\PageData;
use App\Models\Technology;
use Illuminate\Pagination\LengthAwarePaginator;

final class TechnologyQuery
{
    /** @return LengthAwarePaginator<int, Technology> */
    public function get(PageData $page): LengthAwarePaginator
    {
        return Technology::query()->orderBy('slug')->orderBy('id')->paginate($page->perPage, ['id', 'slug', 'name'], 'page', $page->page);
    }
}

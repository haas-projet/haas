<?php

namespace App\Http\Resources\Moderation;

use App\Http\Resources\PaginatedResourceCollection;

final class ReportCollection extends PaginatedResourceCollection
{
    public $collects = ModerationReportResource::class;
}

<?php

namespace App\Data\Moderation;

use App\Data\Common\PageData;

final readonly class ReportFilterData
{
    public function __construct(public PageData $page, public ?string $status, public ?string $category, public ?string $from, public ?string $to) {}
}

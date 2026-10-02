<?php

namespace Tests\Support;

use App\Http\Resources\PaginatedResourceCollection;
use Illuminate\Http\Resources\Attributes\Collects;

#[Collects(UserSummaryResource::class)]
final class UserSummaryCollection extends PaginatedResourceCollection {}

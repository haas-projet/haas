<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\PaginatedResourceCollection;
use Illuminate\Http\Resources\Attributes\Collects;

#[Collects(TechnologyResource::class)]
final class TechnologyCollection extends PaginatedResourceCollection {}

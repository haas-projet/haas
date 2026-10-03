<?php

namespace App\Http\Resources\Identity;

use App\Http\Resources\PaginatedResourceCollection;

final class AdministrationCollection extends PaginatedResourceCollection
{
    public $collects = AdministrationResource::class;
}

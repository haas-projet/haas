<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ReadAccountRequest;
use App\Http\Resources\Identity\AccountAccessResource;
use App\Queries\Identity\AccountAccessQuery;

final class AccountAccessController extends Controller
{
    public function __invoke(ReadAccountRequest $request, AccountAccessQuery $query): AccountAccessResource
    {
        return new AccountAccessResource($query->get());
    }
}

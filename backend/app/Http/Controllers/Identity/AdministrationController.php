<?php

namespace App\Http\Controllers\Identity;

use App\Http\Requests\Identity\AdministrationRequest;
use App\Http\Requests\Identity\ManageAccountRequest;
use App\Http\Resources\Identity\AdministrationCollection;
use App\Http\Resources\Identity\AdministrationResource;
use App\Queries\Identity\AdministrationQuery;
use App\Services\Identity\ManageAccountService;

final class AdministrationController
{
    public function index(AdministrationRequest $request, AdministrationQuery $query): AdministrationCollection
    {
        return new AdministrationCollection($query->members($request->member(), $request->pageData()));
    }

    public function update(ManageAccountRequest $request, string $id, ManageAccountService $service): AdministrationResource
    {
        return new AdministrationResource($service->update($request->member(), $id, $request->decision()));
    }
}

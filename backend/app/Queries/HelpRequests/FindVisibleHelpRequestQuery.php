<?php

namespace App\Queries\HelpRequests;

use App\Models\HelpRequest;
use App\Models\User;
use App\Policies\HelpRequestPolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class FindVisibleHelpRequestQuery
{
    public function __construct(private readonly VisibleHelpRequestsQuery $visible, private readonly HelpRequestPolicy $policy) {}

    public function get(?User $actor, string $id): HelpRequest
    {
        $request = $this->visible->forActor($actor)->findOrFail($id);
        if (! $this->policy->view($actor, $request)) {
            throw (new ModelNotFoundException)->setModel(HelpRequest::class);
        }

        return $request;
    }
}

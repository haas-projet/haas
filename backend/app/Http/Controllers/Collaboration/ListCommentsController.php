<?php

namespace App\Http\Controllers\Collaboration;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\Collaboration\CommentCollection;
use App\Queries\Collaboration\CommentQuery;

final class ListCommentsController extends Controller
{
    public function __invoke(PaginatedRequest $request, CommentQuery $service, string $id): CommentCollection
    {
        return new CommentCollection($service->page($request->user(), $id, $request->pageData()));
    }
}

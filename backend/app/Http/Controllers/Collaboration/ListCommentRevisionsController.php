<?php

namespace App\Http\Controllers\Collaboration;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\Collaboration\CommentRevisionCollection;
use App\Queries\Collaboration\CommentQuery;

final class ListCommentRevisionsController extends Controller
{
    public function __invoke(PaginatedRequest $request, CommentQuery $service, string $id): CommentRevisionCollection
    {
        return new CommentRevisionCollection($service->revisions($request->user(), $id, $request->pageData()));
    }
}

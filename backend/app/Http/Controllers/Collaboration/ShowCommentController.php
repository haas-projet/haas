<?php

namespace App\Http\Controllers\Collaboration;

use App\Http\Controllers\Controller;
use App\Http\Resources\Collaboration\CommentResource;
use App\Queries\Collaboration\VisibleCommentsQuery;
use Illuminate\Http\Request;

final class ShowCommentController extends Controller
{
    public function __invoke(Request $request, VisibleCommentsQuery $service, string $id): CommentResource
    {
        return new CommentResource($service->get($request->user(), $id));
    }
}

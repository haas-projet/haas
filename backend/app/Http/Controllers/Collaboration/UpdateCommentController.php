<?php

namespace App\Http\Controllers\Collaboration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collaboration\UpdateCommentRequest;
use App\Http\Resources\Collaboration\CommentResource;
use App\Services\Collaboration\CommentService;

final class UpdateCommentController extends Controller
{
    public function __invoke(UpdateCommentRequest $request, CommentService $service): CommentResource
    {
        return new CommentResource($service->update($request->member(), $request->targetId(), $request->commentData(), $request->idempotencyKey()));
    }
}

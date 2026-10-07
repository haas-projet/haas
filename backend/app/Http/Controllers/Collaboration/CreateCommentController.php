<?php

namespace App\Http\Controllers\Collaboration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collaboration\StoreCommentRequest;
use App\Http\Resources\Collaboration\CommentResource;
use App\Services\Collaboration\CommentService;
use Illuminate\Http\JsonResponse;

final class CreateCommentController extends Controller
{
    public function __invoke(StoreCommentRequest $request, CommentService $service): JsonResponse
    {
        return (new CommentResource($service->create($request->member(), $request->targetId(), $request->commentData(), $request->idempotencyKey())))->response()->setStatusCode(201);
    }
}

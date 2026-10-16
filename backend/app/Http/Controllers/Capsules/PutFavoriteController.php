<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Http\Controllers\Controller;
use App\Models\Capsules\Capsule;
use App\Models\User;
use App\Services\Capsules\MarkFavoriteService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PutFavoriteController extends Controller
{
    public function __invoke(Request $request, MarkFavoriteService $service, string $capsule): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        $target = Capsule::whereKey($capsule)->firstOrFail();
        $favorite = $service->handle($actor, $target);

        return response()->json(['data' => [
            'capsule_id' => $target->id,
            'marked_at' => $favorite->created_at?->toIso8601String(),
        ]])->setStatusCode(200)->header('Cache-Control', 'no-store, private');
    }
}

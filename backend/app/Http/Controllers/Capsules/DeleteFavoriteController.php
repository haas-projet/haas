<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Http\Controllers\Controller;
use App\Models\Capsules\Capsule;
use App\Models\User;
use App\Services\Capsules\UnmarkFavoriteService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteFavoriteController extends Controller
{
    public function __invoke(Request $request, UnmarkFavoriteService $service, string $capsule): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        $target = Capsule::whereKey($capsule)->firstOrFail();
        $deleted = $service->handle($actor, $target);

        return response()->json(['data' => [
            'capsule_id' => $target->id,
            'deleted' => $deleted,
        ]])->setStatusCode(200)->header('Cache-Control', 'no-store, private');
    }
}

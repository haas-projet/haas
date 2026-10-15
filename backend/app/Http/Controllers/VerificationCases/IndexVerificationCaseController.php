<?php

declare(strict_types=1);

namespace App\Http\Controllers\VerificationCases;

use App\Http\Controllers\Controller;
use App\Http\Resources\VerificationCases\VerificationCaseResource;
use App\Models\User;
use App\Queries\VerificationCases\VisibleVerificationCasesQuery;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class IndexVerificationCaseController extends Controller
{
    public function __invoke(Request $request, VisibleVerificationCasesQuery $query): AnonymousResourceCollection
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min(50, $perPage));
        $cases = $query->forActor($actor)->orderByDesc('created_at')->orderBy('id')->paginate($perPage);

        return VerificationCaseResource::collection($cases)->additional([
            'meta' => [
                'current_page' => $cases->currentPage(),
                'per_page' => $cases->perPage(),
                'last_page' => $cases->lastPage(),
                'total' => $cases->total(),
                'from' => $cases->firstItem(),
                'to' => $cases->lastItem(),
            ],
        ]);
    }
}

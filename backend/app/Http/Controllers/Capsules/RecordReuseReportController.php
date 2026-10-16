<?php

declare(strict_types=1);

namespace App\Http\Controllers\Capsules;

use App\Http\Controllers\Controller;
use App\Http\Requests\Capsules\RecordReuseReportRequest;
use App\Http\Resources\Capsules\ReuseReportResource;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\RecordReuseReportService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RecordReuseReportController extends Controller
{
    public function __invoke(RecordReuseReportRequest $request, RecordReuseReportService $service, string $capsule, string $version): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }
        $target = Capsule::whereKey($capsule)->firstOrFail();
        $draft = CapsuleVersion::whereKey($version)->where('capsule_id', $target->id)->first();
        if ($draft === null) {
            throw new NotFoundHttpException;
        }
        $report = $service->handle($actor, $target, $draft, $request->toData(), $request->idempotencyKey());
        // La révision 1 témoigne d'une création (ou d'un rejeu de la création) ;
        // au-delà, le second POST est une mise à jour avec révision archivée.
        $status = (int) $report->revision === 1 ? 201 : 200;

        return (new ReuseReportResource($report))->response()->setStatusCode($status)->header('Cache-Control', 'no-store, private');
    }
}

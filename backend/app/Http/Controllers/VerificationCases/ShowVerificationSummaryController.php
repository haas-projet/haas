<?php

declare(strict_types=1);

namespace App\Http\Controllers\VerificationCases;

use App\Http\Controllers\Controller;
use App\Http\Resources\VerificationCases\VerificationSummaryResource;
use App\Queries\VerificationCases\VerificationSummaryQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * BV209 — GET /api/v1/versions/{id}/verification-summary. Publique :
 * anonyme et membre voient la même fiche d'une version publiée visible
 * (CAHIER_DES_CHARGES.md:1665). Toute autre id (brouillon, en revue,
 * retirée, capsule masquée, UUID inconnu) renvoie 404 — même format
 * qu'un UUID inconnu, aucun secret dans la réponse (AC45). Aucun
 * paramètre de requête n'est accepté : la fiche est entière ou absente.
 * Cache-Control no-store, private.
 */
final class ShowVerificationSummaryController extends Controller
{
    public function __invoke(Request $request, string $id, VerificationSummaryQuery $query): JsonResponse
    {
        if ($request->query() !== []) {
            throw ValidationException::withMessages([
                'query' => 'La fiche de vérification n\'accepte aucun paramètre de requête.',
            ]);
        }
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            throw new NotFoundHttpException;
        }

        return (new VerificationSummaryResource($query->get($id)))
            ->response($request)
            ->header('Cache-Control', 'no-store, private');
    }
}

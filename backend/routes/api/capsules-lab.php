<?php

// Responsable : capsules/laboratoire. Aucune exécution de code utilisateur.

use App\Http\Controllers\Demo\RecordDemoOrderController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\ProtectSpaRequests;
use Illuminate\Support\Facades\Route;

/*
 * Brique B2 (lot B38) : API de démonstration isolée.
 *
 * Les middlewares `ProtectSpaRequests` et `EnsureAccountIsActive` sont retirés
 * uniquement sur ce groupe : B2 est hors du périmètre des cookies HAAS, servie
 * depuis `demo-api.example.com` et sans session de la plateforme. Le socle HTTP
 * commun (préfixe `/api/v1`, `AssignRequestId`, renderer d'erreurs normalisé)
 * reste actif. Aucune route B2 ne doit lire une donnée métier HAAS.
 *
 * Alternative à arbitrer avec le responsable 1 : déclarer un groupe de
 * middlewares dédié `b2` dans `backend/bootstrap/app.php`, qui n'inclurait
 * ni la session Sanctum stateful, ni les middlewares SPA, ni la vérification
 * du compte. En attendant, `withoutMiddleware` reste localisé à ce fichier
 * et aux routes B2 strictes.
 */
Route::prefix('b2')
    ->withoutMiddleware([ProtectSpaRequests::class, EnsureAccountIsActive::class])
    ->group(function (): void {
        Route::post('demo-orders', RecordDemoOrderController::class)
            ->name('demo.orders.record');
    });

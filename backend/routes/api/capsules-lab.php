<?php

// Responsable : capsules/laboratoire. Aucune exécution de code utilisateur.
// B2 est déclaré exclusivement par le runtime backend/demo, jamais par HAAS.

use App\Http\Controllers\Capsules\PublishCapsuleVersionController;
use App\Http\Controllers\Capsules\RequestChangesController;
use App\Http\Controllers\Capsules\StoreCapsuleDraftController;
use App\Http\Controllers\Capsules\StoreCapsuleVersionDraftController;
use App\Http\Controllers\Capsules\SubmitCapsuleVersionForReviewController;
use App\Http\Controllers\Capsules\UpdateCapsuleVersionDraftController;
use App\Http\Controllers\Comparisons\ListComparisonProfilesController;
use App\Http\Controllers\Comparisons\ShowComparisonRunController;
use App\Http\Controllers\Comparisons\StartComparisonController;
use App\Http\Controllers\Lab\ShowLabDefinitionController;
use App\Http\Controllers\Lab\ShowLabRunController;
use App\Http\Controllers\Lab\StartLabRunController;
use App\Http\Controllers\VerificationCases\IndexVerificationCaseController;
use App\Http\Controllers\VerificationCases\ReviewVerificationCaseController;
use App\Http\Controllers\VerificationCases\ShowVerificationCaseController;
use App\Http\Controllers\VerificationCases\StoreVerificationCaseController;
use App\Http\Controllers\VerificationCases\SubmitVerificationCaseController;
use App\Http\Controllers\VerificationCases\UpdateVerificationCaseController;
use App\Http\Controllers\VerificationCases\WithdrawVerificationCaseController;
use Illuminate\Support\Facades\Route;

// Brouillons de capsule (lots B23/B24). Membre actif/vérifié requis par
// ProtectSpaRequests + EnsureAccountIsActive définis au niveau du groupe api.
Route::prefix('capsules')->group(function (): void {
    Route::post('/', StoreCapsuleDraftController::class)->name('capsules.drafts.store');
    Route::post('{capsule}/versions', StoreCapsuleVersionDraftController::class)
        ->whereUuid('capsule')
        ->name('capsules.versions.drafts.store');
    Route::patch('{capsule}/versions/{version}', UpdateCapsuleVersionDraftController::class)
        ->whereUuid(['capsule', 'version'])
        ->name('capsules.versions.drafts.update');
    Route::post('{capsule}/versions/{version}/submit-review', SubmitCapsuleVersionForReviewController::class)
        ->whereUuid(['capsule', 'version'])
        ->name('capsules.versions.submit-review');
});

// Actions admin : demande de corrections (B24) et publication (B25).
Route::prefix('admin/capsules')->group(function (): void {
    Route::post('{capsule}/versions/{version}/request-changes', RequestChangesController::class)
        ->whereUuid(['capsule', 'version'])
        ->name('admin.capsules.versions.request-changes');
    Route::post('{capsule}/versions/{version}/publish', PublishCapsuleVersionController::class)
        ->whereUuid(['capsule', 'version'])
        ->name('admin.capsules.versions.publish');
});

Route::get('/versions/{id}/lab-definition', ShowLabDefinitionController::class)->whereUuid('id')->name('versions.lab-definition');

// BV204 — profils de comparaison approuvés d'une version publiée visible.
// Lecture seule ; aucun chemin public n'écrit, n'active ou ne retire un
// profil : seule la commande interne lab:bind-comparison-profile le fait.
Route::get('/versions/{id}/comparison-profiles', ListComparisonProfilesController::class)
    ->whereUuid('id')
    ->middleware(['auth:sanctum', 'verified', 'throttle:60,1'])
    ->name('versions.comparison-profiles');

// BV205/BV206 — comparaisons B1. BV205 ouvre la lecture par l'acteur ;
// BV206 ouvre la création idempotente POST /comparisons (202 + suivi).
// Aucune mutation publique n'écrit outcome, input_digest, seed ou
// env_snapshot (CAHIER §45 ligne 1672).
Route::get('/comparisons/{id}', ShowComparisonRunController::class)
    ->whereUuid('id')
    ->middleware(['auth:sanctum', 'verified', 'throttle:60,1'])
    ->name('comparisons.show');
Route::post('/comparisons', StartComparisonController::class)
    ->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])
    ->name('comparisons.store');

// Lancements B34. Membre vérifié actif seulement : pas de code arbitraire,
// pas de scénario choisi par URL. L'écriture des états et résultats reste
// interne (B35/B36). Le GET est réservé au propriétaire du run.
Route::post('/lab-runs', StartLabRunController::class)
    ->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])
    ->name('lab-runs.store');
Route::get('/lab-runs/{id}', ShowLabRunController::class)
    ->whereUuid('id')
    ->middleware(['auth:sanctum', 'verified', 'throttle:60,1'])
    ->name('lab-runs.show');

// BV202 — cas de vérification documentaires. Membre actif et vérifié requis
// par le groupe + 'verified'. Aucune route publique ne passe le cas à
// integrated : voir la commande d'intégration (BV203). L'auteur est fixé
// côté serveur ; les parents sont contrôlés en visibilité à la lecture et
// aux transitions.
Route::prefix('verification-cases')
    ->middleware(['auth:sanctum', 'verified', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('/', IndexVerificationCaseController::class)->name('verification-cases.index');
        Route::post('/', StoreVerificationCaseController::class)->name('verification-cases.store');
        Route::get('{verification_case}', ShowVerificationCaseController::class)
            ->whereUuid('verification_case')
            ->name('verification-cases.show');
        Route::patch('{verification_case}', UpdateVerificationCaseController::class)
            ->whereUuid('verification_case')
            ->name('verification-cases.update');
        Route::post('{verification_case}/submit', SubmitVerificationCaseController::class)
            ->whereUuid('verification_case')
            ->name('verification-cases.submit');
        Route::post('{verification_case}/withdraw', WithdrawVerificationCaseController::class)
            ->whereUuid('verification_case')
            ->name('verification-cases.withdraw');
    });

// BV203 — revue admin. Décision submitted → reviewed/declined par un
// modérateur ou admin tiers (auto-revue refusée). L'intégration (integrated)
// n'est jamais joignable depuis une route publique : voir la commande
// d'intégration lab:bind-case.
Route::prefix('admin/verification-cases')
    ->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])
    ->group(function (): void {
        Route::post('{verification_case}/review', ReviewVerificationCaseController::class)
            ->whereUuid('verification_case')
            ->name('admin.verification-cases.review');
    });

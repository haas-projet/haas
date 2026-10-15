<?php

// Responsable : capsules/laboratoire. Aucune exécution de code utilisateur.
// B2 est déclaré exclusivement par le runtime backend/demo, jamais par HAAS.

use App\Http\Controllers\Capsules\PublishCapsuleVersionController;
use App\Http\Controllers\Capsules\RequestChangesController;
use App\Http\Controllers\Capsules\StoreCapsuleDraftController;
use App\Http\Controllers\Capsules\StoreCapsuleVersionDraftController;
use App\Http\Controllers\Capsules\SubmitCapsuleVersionForReviewController;
use App\Http\Controllers\Capsules\UpdateCapsuleVersionDraftController;
use App\Http\Controllers\Lab\ShowLabDefinitionController;
use App\Http\Controllers\Lab\ShowLabRunController;
use App\Http\Controllers\Lab\StartLabRunController;
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

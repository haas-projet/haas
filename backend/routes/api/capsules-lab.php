<?php

// Responsable : capsules/laboratoire. Aucune exécution de code utilisateur.
// B2 est déclaré exclusivement par le runtime backend/demo, jamais par HAAS.

use App\Http\Controllers\Capsules\ListVisibleCapsulesController;
use App\Http\Controllers\Capsules\PublishCapsuleVersionController;
use App\Http\Controllers\Capsules\RequestChangesController;
use App\Http\Controllers\Capsules\ShowVisibleCapsuleController;
use App\Http\Controllers\Capsules\StoreCapsuleDraftController;
use App\Http\Controllers\Capsules\StoreCapsuleVersionDraftController;
use App\Http\Controllers\Capsules\SubmitCapsuleVersionForReviewController;
use App\Http\Controllers\Capsules\UpdateCapsuleVersionDraftController;
use Illuminate\Support\Facades\Route;

// Catalogue public (B26) : accessible aux visiteurs, aucune carte ne porte
// d'en-tête personnalisé et seules les versions publiées sont exposées.
Route::prefix('capsules')->group(function (): void {
    Route::get('/', ListVisibleCapsulesController::class)->name('capsules.catalogue.index');
    Route::get('{slug}', ShowVisibleCapsuleController::class)
        ->where('slug', '^[a-z0-9][a-z0-9-]{1,118}[a-z0-9]$')
        ->name('capsules.catalogue.show');
    Route::get('{slug}/versions/{version}', ShowVisibleCapsuleController::class)
        ->where(['slug' => '^[a-z0-9][a-z0-9-]{1,118}[a-z0-9]$', 'version' => '^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)$'])
        ->name('capsules.catalogue.show.version');
});

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

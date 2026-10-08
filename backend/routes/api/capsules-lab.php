<?php

// Responsable : capsules/laboratoire. Aucune exécution de code utilisateur.

use App\Http\Controllers\Capsules\PublishCapsuleVersionController;
use App\Http\Controllers\Capsules\RequestChangesController;
use App\Http\Controllers\Capsules\StoreCapsuleDraftController;
use App\Http\Controllers\Capsules\StoreCapsuleVersionDraftController;
use App\Http\Controllers\Capsules\SubmitCapsuleVersionForReviewController;
use App\Http\Controllers\Capsules\UpdateCapsuleVersionDraftController;
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

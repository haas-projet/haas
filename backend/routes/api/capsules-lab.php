<?php

// Responsable : capsules/laboratoire. Aucune exécution de code utilisateur.

use App\Http\Controllers\Capsules\StoreCapsuleDraftController;
use App\Http\Controllers\Capsules\StoreCapsuleVersionDraftController;
use Illuminate\Support\Facades\Route;

// Brouillons de capsule (lot B23). Membre actif/vérifié requis par
// ProtectSpaRequests + EnsureAccountIsActive définis au niveau du groupe api.
Route::prefix('capsules')->group(function (): void {
    Route::post('/', StoreCapsuleDraftController::class)->name('capsules.drafts.store');
    Route::post('{capsule}/versions', StoreCapsuleVersionDraftController::class)
        ->whereUuid('capsule')
        ->name('capsules.versions.drafts.store');
});

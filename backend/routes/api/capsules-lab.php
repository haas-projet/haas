<?php

// Responsable : capsules/laboratoire. Aucune exécution de code utilisateur.
// B2 est déclaré exclusivement par le runtime backend/demo, jamais par HAAS.

use App\Http\Controllers\Capsules\DeleteFavoriteController;
use App\Http\Controllers\Capsules\IssueArtifactDownloadController;
use App\Http\Controllers\Capsules\ListVisibleCapsulesController;
use App\Http\Controllers\Capsules\PublishCapsuleVersionController;
use App\Http\Controllers\Capsules\PutFavoriteController;
use App\Http\Controllers\Capsules\RecordReuseReportController;
use App\Http\Controllers\Capsules\RequestChangesController;
use App\Http\Controllers\Capsules\ShowVisibleCapsuleController;
use App\Http\Controllers\Capsules\StoreCapsuleDraftController;
use App\Http\Controllers\Capsules\StoreCapsuleVersionDraftController;
use App\Http\Controllers\Capsules\StreamArtifactDownloadController;
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
use App\Http\Controllers\VerificationCases\ShowVerificationSummaryController;
use App\Http\Controllers\VerificationCases\StoreVerificationCaseController;
use App\Http\Controllers\VerificationCases\SubmitVerificationCaseController;
use App\Http\Controllers\VerificationCases\UpdateVerificationCaseController;
use App\Http\Controllers\VerificationCases\WithdrawVerificationCaseController;
use Illuminate\Support\Facades\Route;

// Catalogue public (B26) : accessible aux visiteurs. Le groupe api applique
// AssignRequestId, EnsureFrontendRequestsAreStateful (statefulApi),
// SubstituteBindings, EnsureAccountIsActive et ProtectSpaRequests ; aucun
// quota ni authentification au niveau du groupe. On ajoute donc un throttle
// local (60 req/min par IP ou user) pour borner la lecture publique.
Route::prefix('capsules')->middleware(['throttle:60,1'])->group(function (): void {
    Route::get('/', ListVisibleCapsulesController::class)->name('capsules.catalogue.index');
    Route::get('{slug}', ShowVisibleCapsuleController::class)
        ->where('slug', '^[a-z0-9][a-z0-9-]{1,118}[a-z0-9]$')
        ->name('capsules.catalogue.show');
    Route::get('{slug}/versions/{version}', ShowVisibleCapsuleController::class)
        ->where(['slug' => '^[a-z0-9][a-z0-9-]{1,118}[a-z0-9]$', 'version' => '^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)$'])
        ->name('capsules.catalogue.show.version');
});

// Brouillons de capsule (lots B23/B24). ProtectSpaRequests + EnsureAccountIsActive
// (groupe api) ne refusent pas l'anonyme : auth:sanctum doit intercepter en
// premier (401 et non 403) ; verified rejette un compte non vérifié (403) et
// un quota borne l'écriture de brouillons.
Route::prefix('capsules')->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->group(function (): void {
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

// Actions admin : demande de corrections (B24) et publication (B25). L'auth
// doit refuser l'anonyme en 401 avant toute validation ; les Policies des
// contrôleurs/services continuent d'enforcer le rôle modérateur/admin.
Route::prefix('admin/capsules')->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->group(function (): void {
    Route::post('{capsule}/versions/{version}/request-changes', RequestChangesController::class)
        ->whereUuid(['capsule', 'version'])
        ->name('admin.capsules.versions.request-changes');
    Route::post('{capsule}/versions/{version}/publish', PublishCapsuleVersionController::class)
        ->whereUuid(['capsule', 'version'])
        ->name('admin.capsules.versions.publish');
});

// Téléchargement contrôlé du kit (B27, CAHIER_DES_CHARGES.md:956). Membre
// actif/vérifié obligatoire par ProtectSpaRequests + EnsureAccountIsActive +
// `verified`. L'URL signée pointe vers une route distincte qui réévalue tous
// les droits à chaque accès (AC15/AC25).
Route::prefix('capsules')->middleware(['auth:sanctum', 'verified', 'throttle:artifact-downloads'])->group(function (): void {
    Route::get('{capsule}/versions/{version}/artifact/{artifact}', IssueArtifactDownloadController::class)
        ->whereUuid(['capsule', 'version', 'artifact'])
        ->name('capsules.versions.artifact.issue');
});
Route::prefix('capsules')->middleware(['auth:sanctum', 'verified', 'throttle:artifact-downloads', 'signed:relative'])->group(function (): void {
    Route::get('{capsule}/versions/{version}/artifact/{artifact}/stream', StreamArtifactDownloadController::class)
        ->whereUuid(['capsule', 'version', 'artifact'])
        ->name('capsules.versions.artifact.stream');
    Route::get('{capsule}/versions/{version}/artifact/{artifact}/notices', StreamArtifactDownloadController::class)
        ->whereUuid(['capsule', 'version', 'artifact'])
        ->name('capsules.versions.artifact.notices');
});

// Favoris privés et retours humains (B28). auth:sanctum doit refuser
// l'anonyme en 401 avant verified (sinon le middleware verified répond 403
// à un visiteur, cf. finding BV209) ; un quota borne les écritures.
Route::prefix('capsules')->middleware(['auth:sanctum', 'verified', 'throttle:30,1'])->group(function (): void {
    Route::put('{capsule}/favorite', PutFavoriteController::class)
        ->whereUuid('capsule')
        ->name('capsules.favorite.put');
    Route::delete('{capsule}/favorite', DeleteFavoriteController::class)
        ->whereUuid('capsule')
        ->name('capsules.favorite.delete');
    Route::post('{capsule}/versions/{version}/reuse-reports', RecordReuseReportController::class)
        ->whereUuid(['capsule', 'version'])
        ->name('capsules.versions.reuse-reports.store');
});

// Lecture publique de la définition laboratoire (B33) : accessible aux
// visiteurs via le groupe api (aucune pile d'auth) ; on borne la lecture
// par un throttle local.
Route::get('/versions/{id}/lab-definition', ShowLabDefinitionController::class)
    ->whereUuid('id')
    ->middleware(['throttle:60,1'])
    ->name('versions.lab-definition');

// BV209 — fiche de vérification versionnée. Publique (anonyme et membre
// voient la même fiche d'une version publiée visible) ; versions brouillons,
// en revue, retirées ou appartenant à une capsule masquée = 404. Aucun
// paramètre de requête accepté (422). Cache-Control no-store, private.
Route::get('/versions/{id}/verification-summary', ShowVerificationSummaryController::class)
    ->whereUuid('id')
    ->middleware(['throttle:60,1'])
    ->name('versions.verification-summary');

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

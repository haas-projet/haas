<?php

use App\Data\Capsules\CapsuleDraftData;
use App\Data\Capsules\PublishCommandData;
use App\Data\Capsules\ReviewCommandData;
use App\Data\Capsules\UpdateDraftData;
use App\Data\Capsules\VersionDraftData;
use App\Data\Idempotency\IdempotencyKey;
use App\Enums\Capsules\CapsuleSourceKind;
use App\Exceptions\Capsules\InsufficientDraftContent;
use App\Exceptions\Capsules\StaleCapsuleVersion;
use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\User;
use App\Services\Capsules\CreateCapsuleDraftService;
use App\Services\Capsules\PublishCapsuleVersionService;
use App\Services\Capsules\RequestChangesOnCapsuleVersionService;
use App\Services\Capsules\SubmitCapsuleVersionForReviewService;
use App\Services\Capsules\UpdateCapsuleVersionDraftService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
DB::statement("SET application_name = 'haas_b24_capsule_worker'");
echo "READY\n";
flush();
$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_string($input['actor_id'] ?? null) || ! is_string($input['mode'] ?? null)) {
    throw new RuntimeException('Charge de fixture concurrente invalide.');
}
try {
    $actor = User::findOrFail($input['actor_id']);
    switch ($input['mode']) {
        case 'create_draft':
            app(CreateCapsuleDraftService::class)->handle(
                $actor,
                new CapsuleDraftData(
                    slug: $input['slug'],
                    sourceKind: CapsuleSourceKind::Editorial,
                    sourceRequestId: null,
                    editorialOrigin: 'Démonstration concurrente',
                    version: new VersionDraftData(
                        versionLabel: '1.0.0',
                        body: "## Procédure\n\nÉtape documentée pour la fixture concurrente.",
                        limits: 'Portée réduite au scénario cité.',
                        technologies: [],
                    ),
                ),
                new IdempotencyKey($input['idempotency_key']),
            );
            break;
        case 'update_draft':
            app(UpdateCapsuleVersionDraftService::class)->handle(
                $actor,
                Capsule::whereKey($input['capsule_id'])->firstOrFail(),
                CapsuleVersion::whereKey($input['version_id'])->firstOrFail(),
                new UpdateDraftData(
                    lockVersion: $input['lock_version'],
                    body: "## Procédure concurrente\n\nContenu de la fixture concurrente (".$input['nonce'].').',
                    limits: null,
                    technologies: null,
                ),
            );
            break;
        case 'submit_review':
            app(SubmitCapsuleVersionForReviewService::class)->handle(
                $actor,
                Capsule::whereKey($input['capsule_id'])->firstOrFail(),
                CapsuleVersion::whereKey($input['version_id'])->firstOrFail(),
                new ReviewCommandData($input['lock_version']),
                new IdempotencyKey($input['idempotency_key']),
            );
            break;
        case 'request_changes':
            app(RequestChangesOnCapsuleVersionService::class)->handle(
                $actor,
                Capsule::whereKey($input['capsule_id'])->firstOrFail(),
                CapsuleVersion::whereKey($input['version_id'])->firstOrFail(),
                new ReviewCommandData($input['lock_version'], $input['note']),
                new IdempotencyKey($input['idempotency_key']),
            );
            break;
        case 'publish':
            app(PublishCapsuleVersionService::class)->handle(
                $actor,
                Capsule::whereKey($input['capsule_id'])->firstOrFail(),
                CapsuleVersion::whereKey($input['version_id'])->firstOrFail(),
                new PublishCommandData($input['lock_version']),
                new IdempotencyKey($input['idempotency_key']),
            );
            break;
        default:
            throw new RuntimeException('Mode de fixture concurrente inconnu.');
    }
    echo "UPDATED\n";
} catch (StaleCapsuleVersion|AuthorizationException|IdempotencyConflict|InsufficientDraftContent) {
    echo "CONFLICT\n";
}

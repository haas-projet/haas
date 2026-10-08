<?php

declare(strict_types=1);

namespace App\Services\Capsules;

use App\Data\Capsules\PublishCommandData;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Data\Idempotency\StoredCommandResult;
use App\Enums\Capsules\ArtifactDistributionStatus;
use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\ReviewDecision;
use App\Exceptions\Capsules\InsufficientDraftContent;
use App\Exceptions\Capsules\StaleCapsuleVersion;
use App\Models\Capsules\Capsule;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Capsules\CapsuleVersionReview;
use App\Models\Technology;
use App\Models\User;
use App\Policies\CapsulePolicy;
use App\Services\Idempotency\IdempotencyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * B25 — « Publier une version immuable ».
 *
 * Transition `in_review → published` par un modérateur/admin indépendant
 * (AC12, CAHIER_DES_CHARGES.md:462 et :955). Dans la transaction :
 *  - verrou de la version et de la capsule, revue d'autorisation,
 *    contrôle du `lock_version` ;
 *  - documentation complète (body ≥ 20 non blancs, limits non vide) et
 *    provenance active (si source help_request, résolution non révoquée) ;
 *  - artefacts : chaque ligne `approved` doit avoir `notices_path` défini
 *    (le `sha256` est non nul par contrainte de B22). La publication ne
 *    change JAMAIS `distribution_status` ;
 *  - calcul du `content_digest` (sha256 hex d'une sérialisation canonique
 *    `{version_label, body, limits, technologies[trié]}`) ;
 *  - écriture `state=published`, `reviewer_id`, `published_at`,
 *    décision `approved` dans `capsule_version_reviews`, audit.
 *
 * RM03 appliqué : la version publiée est immuable, aucune preuve ni
 * définition de laboratoire n'est copiée vers une nouvelle version
 * (c'est le travail de B33, hors de ce lot). Aucune notification
 * (Q12 reste ouverte pour la décision « approved »).
 */
final class PublishCapsuleVersionService
{
    public function __construct(
        private readonly CapsulePolicy $policy,
        private readonly WriteCapsuleAudit $audit,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function handle(User $actor, Capsule $capsule, CapsuleVersion $version, PublishCommandData $command, IdempotencyKey $key): CapsuleVersion
    {
        return $this->idempotency->execute(
            $actor,
            new IdempotencyData(
                'POST /api/v1/admin/capsules/'.$capsule->id.'/versions/'.$version->id.'/publish',
                $key,
                ['lock_version' => $command->lockVersion],
            ),
            function (User $current) use ($capsule, $version): void {
                $sourceCapsule = Capsule::whereKey($capsule->id)->lockForUpdate()->firstOrFail();
                $locked = CapsuleVersion::whereKey($version->id)->where('capsule_id', $sourceCapsule->id)->lockForUpdate()->firstOrFail();
                if (! $this->policy->publishVersion($current, $sourceCapsule, $locked->id)) {
                    throw new AuthorizationException;
                }
            },
            function (User $current) use ($capsule, $version, $command): StoredCommandResult {
                $sourceCapsule = Capsule::whereKey($capsule->id)->firstOrFail();
                $locked = CapsuleVersion::whereKey($version->id)->where('capsule_id', $sourceCapsule->id)->firstOrFail();
                if (! $this->policy->publishVersion($current, $sourceCapsule, $locked->id)) {
                    throw new AuthorizationException;
                }
                if ($locked->lock_version !== $command->lockVersion) {
                    throw new StaleCapsuleVersion('Cette version a été modifiée. Rechargez sa dernière forme.');
                }
                if ($locked->state !== CapsuleVersionState::InReview) {
                    throw new StaleCapsuleVersion('La version n\'est pas en revue : publication refusée.');
                }
                $missing = $this->missingContent($locked);
                if ($missing !== []) {
                    throw new InsufficientDraftContent(implode('|', $missing));
                }
                $this->ensureProvenanceStillActive($sourceCapsule);
                $this->ensureArtifactsReadyForDistribution($locked->id);

                $publishedAt = now()->utc();
                $digest = $this->computeContentDigest($locked);
                $locked->state = CapsuleVersionState::Published;
                $locked->reviewer_id = $current->id;
                $locked->published_at = $publishedAt;
                $locked->content_digest = $digest;
                $locked->lock_version = $locked->lock_version + 1;
                $locked->save();

                $review = (new CapsuleVersionReview)->forceFill([
                    'version_id' => $locked->id,
                    'reviewer_id' => $current->id,
                    'decision' => ReviewDecision::Approved,
                    'note' => null,
                    'reviewed_lock_version' => $locked->lock_version,
                    'created_at' => $publishedAt,
                ]);
                $review->save();

                $this->audit->capsuleVersion($current, $locked->id, 'capsule.version.published', [
                    'capsule_id' => $sourceCapsule->id,
                    'review_id' => $review->id,
                    'lock_version' => $locked->lock_version,
                    'content_digest' => $digest,
                ]);

                return new StoredCommandResult(
                    200,
                    ['capsule_id' => $sourceCapsule->id, 'version_id' => $locked->id, 'review_id' => $review->id, 'content_digest' => $digest],
                    $locked->lock_version,
                );
            },
            function (User $current, StoredCommandResult $stored): CapsuleVersion {
                $locked = CapsuleVersion::whereKey($stored->references['version_id'])->firstOrFail();
                if ($locked->state !== CapsuleVersionState::Published || $locked->lock_version !== $stored->version) {
                    throw new StaleCapsuleVersion('La version a changé depuis cette publication.');
                }

                return $locked->load('technologies');
            },
        );
    }

    /** @return list<string> */
    private function missingContent(CapsuleVersion $version): array
    {
        $missing = [];
        if (trim($version->body) === '' || mb_strlen(trim($version->body)) < 20) {
            $missing[] = 'body';
        }
        if ($version->limits === null || trim($version->limits) === '') {
            $missing[] = 'limits';
        }

        return $missing;
    }

    /**
     * Si la capsule est issue d'une demande d'aide, la résolution doit toujours
     * être active (`revoked_at IS NULL`). Les capsules éditoriales n'ont pas
     * d'autre contrôle de provenance ici (owner / editorial_origin déjà posés).
     */
    private function ensureProvenanceStillActive(Capsule $capsule): void
    {
        if ($capsule->source_request_id === null) {
            return;
        }
        $active = DB::table('resolutions')
            ->where('request_id', $capsule->source_request_id)
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->exists();
        if (! $active) {
            throw ValidationException::withMessages([
                'source_request_id' => ['La demande source doit porter une résolution active.'],
            ]);
        }
    }

    /**
     * Toute ligne `artifacts` approuvée doit porter `sha256` (contrainte
     * NOT NULL de la table) ET `notices_path` renseigné. Les lignes
     * `inactive` restent recevables sans modification.
     */
    private function ensureArtifactsReadyForDistribution(string $versionId): void
    {
        $invalid = DB::table('artifacts')
            ->where('version_id', $versionId)
            ->where('distribution_status', ArtifactDistributionStatus::Approved->value)
            ->where(function ($query): void {
                $query->whereNull('notices_path');
            })
            ->exists();
        if ($invalid) {
            throw ValidationException::withMessages([
                'artifacts' => ['Un artefact approuvé doit porter un chemin de notices.'],
            ]);
        }
    }

    private function computeContentDigest(CapsuleVersion $version): string
    {
        $technologies = $version->load('technologies')->technologies->map(static function (Technology $tech): array {
            /** @var Pivot $pivot */
            $pivot = $tech->getRelation('pivot');

            return [
                'technology_id' => (string) $tech->id,
                'version_label' => $pivot->getAttribute('version_label'),
            ];
        })->sortBy('technology_id')->values()->all();

        $payload = [
            'version_label' => $version->version_label,
            'body' => $version->body,
            'limits' => $version->limits,
            'technologies' => $technologies,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}

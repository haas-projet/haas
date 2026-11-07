<?php

declare(strict_types=1);

namespace App\Queries\Comparisons;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\Identity\AccountStatus;
use App\Models\Capsules\CapsuleVersion;
use App\Models\Comparisons\ComparisonProfile;
use App\Queries\HelpRequests\VisibleHelpRequestsQuery;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lecture des profils compatibles d'une version publiée. La visibilité suit
 * CAHIER_DES_CHARGES.md:1670 : « profils compatibles et approuvés uniquement ».
 * Une version non publiée, retirée, dont la capsule n'est pas visible ou dont
 * l'auteur est inactif n'est jamais révélée (404, pas 403).
 */
final class ComparisonProfileQuery
{
    public function __construct(private readonly VisibleHelpRequestsQuery $requests) {}

    /** @return list<ComparisonProfile> */
    public function listForVersion(string $versionId): array
    {
        $version = CapsuleVersion::on('pgsql')->whereKey($versionId)->where('state', CapsuleVersionState::Published)
            ->whereHas('capsule', function (Builder $capsule): void {
                $capsule->where('visibility', CapsuleVisibility::Visible)
                    ->whereHas('owner', fn (Builder $owner) => $owner->where('status', AccountStatus::Active)->whereNotNull('email_verified_at'))
                    ->where(fn (Builder $source) => $source->whereNull('source_request_id')
                        ->orWhereIn('source_request_id', $this->requests->forActor(null)->select('help_requests.id')));
            })->first();
        if ($version === null) {
            throw new NotFoundHttpException;
        }

        return array_values(ComparisonProfile::query()
            ->where('candidate_version_id', $version->id)
            ->where('enabled', true)
            ->orderBy('created_at')
            ->get()
            ->all());
    }
}

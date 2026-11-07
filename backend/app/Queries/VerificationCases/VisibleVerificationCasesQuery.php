<?php

declare(strict_types=1);

namespace App\Queries\VerificationCases;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\AccountStatus;
use App\Enums\VerificationCases\VerificationCaseState;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use Illuminate\Database\Eloquent\Builder;

// BV202 — liste filtrée par visibilité. Un cas est visible si l'acteur est
// actif/vérifié, le cas n'est ni retiré ni en brouillon d'un autre acteur, et
// son parent reste visible (demande ouverte ou version publiée).
final class VisibleVerificationCasesQuery
{
    /** @return Builder<VerificationCase> */
    public function forActor(User $actor): Builder
    {
        return VerificationCase::query()
            ->where(function (Builder $visible) use ($actor): void {
                $visible->where(function (Builder $public): void {
                    $public->whereNotIn('state', [VerificationCaseState::Draft, VerificationCaseState::Withdrawn]);
                })->orWhere('author_id', $actor->id);
            })
            ->where(function (Builder $parent): void {
                $parent->whereExists(function ($q): void {
                    $q->selectRaw('1')
                        ->from('help_requests')
                        ->join('users', 'users.id', '=', 'help_requests.author_id')
                        ->whereColumn('help_requests.id', 'verification_cases.help_request_id')
                        ->whereNull('help_requests.hidden_at')
                        ->whereNotIn('help_requests.state', [HelpRequestState::Draft])
                        ->where('users.status', AccountStatus::Active->value)
                        ->whereNotNull('users.email_verified_at');
                })->orWhereExists(function ($q): void {
                    $q->selectRaw('1')
                        ->from('capsule_versions')
                        ->join('capsules', 'capsules.id', '=', 'capsule_versions.capsule_id')
                        ->whereColumn('capsule_versions.id', 'verification_cases.capsule_version_id')
                        ->where('capsule_versions.state', CapsuleVersionState::Published->value)
                        ->where('capsules.visibility', CapsuleVisibility::Visible->value);
                });
            })
            ->with(['author:id,handle', 'revisions' => fn ($q) => $q->orderBy('revision_no')]);
    }
}

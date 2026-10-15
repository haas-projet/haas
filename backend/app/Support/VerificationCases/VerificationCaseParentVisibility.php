<?php

declare(strict_types=1);

namespace App\Support\VerificationCases;

use App\Enums\Capsules\CapsuleVersionState;
use App\Enums\Capsules\CapsuleVisibility;
use App\Enums\HelpRequests\HelpRequestState;
use App\Enums\Identity\AccountStatus;
use App\Exceptions\VerificationCases\VerificationCaseNotFound;
use App\Models\User;
use App\Support\Identity\MemberAccess;
use Illuminate\Support\Facades\DB;

// Visibilité du parent d'un cas, recontrôlée à la création, à la lecture, à la
// soumission et au retrait (CAHIER_DES_CHARGES.md:1629 et :1635). Un parent
// masqué, retiré, ou une version non publiée = introuvable (jamais 403 pour
// ne pas révéler l'existence).
final class VerificationCaseParentVisibility
{
    public function __construct(private readonly MemberAccess $access) {}

    public function assertHelpRequestVisible(?User $actor, string $helpRequestId): void
    {
        // `help_requests.author_id` est NOT NULL (FK restrictOnDelete en B11) :
        // join interne obligatoire ; PostgreSQL refuse FOR SHARE sur une
        // jointure externe potentiellement nulle.
        $request = DB::table('help_requests')
            ->join('users', 'users.id', '=', 'help_requests.author_id')
            ->select([
                'help_requests.id',
                'help_requests.author_id',
                'help_requests.state',
                'help_requests.hidden_at',
                'users.status as author_status',
                'users.email_verified_at as author_verified_at',
            ])
            ->where('help_requests.id', $helpRequestId)
            ->sharedLock()
            ->first();
        if ($request === null || $request->hidden_at !== null
            || $request->author_status !== AccountStatus::Active->value
            || $request->author_verified_at === null) {
            throw new VerificationCaseNotFound;
        }
        if ($request->state === HelpRequestState::Draft->value
            && ($actor === null || $actor->id !== $request->author_id)) {
            throw new VerificationCaseNotFound;
        }
    }

    public function assertCapsuleVersionVisible(?User $actor, string $versionId): void
    {
        if (! $this->access->verified($actor)) {
            throw new VerificationCaseNotFound;
        }
        $version = DB::table('capsule_versions')
            ->join('capsules', 'capsules.id', '=', 'capsule_versions.capsule_id')
            ->select([
                'capsule_versions.id',
                'capsule_versions.state',
                'capsules.visibility as capsule_visibility',
            ])
            ->where('capsule_versions.id', $versionId)
            ->sharedLock()
            ->first();
        if ($version === null
            || $version->state !== CapsuleVersionState::Published->value
            || $version->capsule_visibility !== CapsuleVisibility::Visible->value) {
            throw new VerificationCaseNotFound;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Policies\VerificationCases;

use App\Enums\Identity\Role;
use App\Enums\VerificationCases\VerificationCaseState;
use App\Models\User;
use App\Models\VerificationCases\VerificationCase;
use App\Support\Identity\MemberAccess;

final class VerificationCasePolicy
{
    public function __construct(private readonly MemberAccess $access) {}

    public function create(User $actor): bool
    {
        return $this->access->verified($actor);
    }

    public function review(User $actor, VerificationCase $case): bool
    {
        // Relecteur distinct de l'auteur (auto-revue interdite, y compris pour
        // un admin). Rôle modérateur ou admin requis, actif et vérifié.
        return $this->access->verified($actor)
            && $actor->id !== $case->author_id
            && in_array($actor->role, [Role::Moderator, Role::Admin], true);
    }

    public function view(?User $actor, VerificationCase $case): bool
    {
        if (! $this->access->verified($actor)) {
            return false;
        }
        if ($case->state === VerificationCaseState::Withdrawn && ! $this->access->owns($actor, $case->author_id)) {
            return false;
        }
        if ($case->state === VerificationCaseState::Draft && ! $this->access->owns($actor, $case->author_id)) {
            return false;
        }

        return true;
    }

    public function update(User $actor, VerificationCase $case): bool
    {
        // La vérification d'état (draft, submitted) revient au Service pour
        // qu'un rejeu idempotent voie toujours la ressource transitionnée et
        // revienne le même 2xx que la première passe.
        return $this->access->owns($actor, $case->author_id);
    }

    public function submit(User $actor, VerificationCase $case): bool
    {
        return $this->access->owns($actor, $case->author_id);
    }

    public function withdraw(User $actor, VerificationCase $case): bool
    {
        return $this->access->owns($actor, $case->author_id);
    }
}

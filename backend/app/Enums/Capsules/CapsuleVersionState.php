<?php

declare(strict_types=1);

namespace App\Enums\Capsules;

enum CapsuleVersionState: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Published = 'published';
    case Withdrawn = 'withdrawn';

    /** @return list<self> */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Draft => [self::InReview],
            self::InReview => [self::ChangesRequested, self::Published],
            self::ChangesRequested => [self::InReview],
            self::Published => [self::Withdrawn],
            self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }
}

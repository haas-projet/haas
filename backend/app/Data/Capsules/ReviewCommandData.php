<?php

declare(strict_types=1);

namespace App\Data\Capsules;

use InvalidArgumentException;

final readonly class ReviewCommandData
{
    public function __construct(public int $lockVersion, public ?string $note = null)
    {
        if ($lockVersion < 1 || $lockVersion > 2147483646) {
            throw new InvalidArgumentException('lock_version hors bornes.');
        }
        if ($note !== null && (mb_strlen(trim($note)) < 20 || mb_strlen($note) > 2000)) {
            throw new InvalidArgumentException('La note de revue doit contenir de 20 à 2000 caractères.');
        }
    }
}

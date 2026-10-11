<?php

declare(strict_types=1);

namespace App\Data\Capsules;

use InvalidArgumentException;

/**
 * Charge de commande pour la publication d'une version de capsule.
 * Seul `lock_version` est accepté : la publication n'ajoute ni note
 * ni contenu éditorial (CAHIER_DES_CHARGES.md:468 traite la
 * documentation côté brouillon). La note de revue éventuelle est
 * documentaire et reste absente du canal `approved`.
 */
final readonly class PublishCommandData
{
    public function __construct(public int $lockVersion)
    {
        if ($lockVersion < 1 || $lockVersion > 2147483646) {
            throw new InvalidArgumentException('lock_version hors bornes.');
        }
    }
}

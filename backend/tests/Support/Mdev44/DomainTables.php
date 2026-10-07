<?php

declare(strict_types=1);

namespace Tests\Support\Mdev44;

use Illuminate\Support\Facades\Schema;

/**
 * Chaque lot ajoute ses tables ici, enfants avant parents.
 * Utilisé par les tests de migration partagés (socle B05, schéma B11) pour
 * déposer les consommateurs du domaine capsules/laboratoire avant un rollback.
 */
final class DomainTables
{
    /** @var list<string> */
    private const TABLES = [
        'artifacts',
        'capsule_contributors',
        'capsule_versions',
        'capsules',
    ];

    public static function dropAll(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }
}

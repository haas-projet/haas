<?php

declare(strict_types=1);

namespace Tests\Support\Mdev44;

use Illuminate\Support\Facades\Schema;
use Tests\Support\TestDatabaseGuard;

/**
 * Chaque lot ajoute ses tables ici, enfants avant parents.
 * Utilisé par les tests de migration partagés (socle B05, schéma B11) pour
 * déposer les consommateurs du domaine capsules/laboratoire avant un rollback.
 */
final class DomainTables
{
    /** @var list<string> */
    private const TABLES = [
        'case_scenario_links',
        'verification_case_revisions',
        'verification_cases',
        'lab_results',
        'lab_runs',
        'lab_definitions',
        'comparison_profiles',
        'capsule_version_reviews',
        'capsule_version_technologies',
        'artifacts',
        'capsule_contributors',
        'capsule_versions',
        'capsules',
    ];

    public static function dropAll(): void
    {
        TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }
}

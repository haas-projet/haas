<?php

declare(strict_types=1);

namespace App\Infrastructure\Comparisons;

use App\Infrastructure\Lab\ApprovedLabRegistry;

/**
 * Registre immuable des profils de comparaison approuvés (BV204).
 *
 * Fermé en code, jamais alimenté par l'interface : HAAS n'offre pas de route
 * d'écriture publique. Seules la famille, l'empreinte de la suite candidate,
 * l'empreinte du module candidat et la version du schéma d'entrée sont fixées
 * ici. La « référence incorrecte » (CAHIER_DES_CHARGES.md:1643) n'est pas
 * livrée dans le dépôt ; la constante BASELINE_SOURCE_COMMIT reste null tant
 * qu'un manifeste de baseline réel n'est pas joint à une release. Dans cet
 * état, bindComparisonProfile() refuse en production ; les tests passent par
 * une fixture explicitement marquée test_only_fixture (voir le gabarit
 * ops/templates/release-manifest.example.json).
 */
final class ComparisonProfileRegistry
{
    public const FAMILY_KEY = 'b1.idempotent.v1';

    public const INPUT_SCHEMA_VERSION = '1.0.0';

    public function __construct(private readonly ApprovedLabRegistry $lab) {}

    public function familyKey(): string
    {
        return self::FAMILY_KEY;
    }

    public function inputSchemaVersion(): string
    {
        return self::INPUT_SCHEMA_VERSION;
    }

    public function expectedCandidateSuiteDigest(): string
    {
        return $this->lab->manifest()['suite_digest'];
    }

    public function expectedCandidateCodeDigest(): string
    {
        return $this->lab->manifest()['code_digest'];
    }

    public function expectedCandidateRelease(): string
    {
        return $this->lab->manifest()['release'];
    }

    public function expectedCandidateSourceCommit(): string
    {
        return $this->lab->manifest()['source_commit'];
    }

    /**
     * Empreinte source attendue de la baseline. Null tant qu'aucune baseline
     * réelle n'a été intégrée au registre ; un manifeste de release doit
     * déclarer le même commit, sans quoi la liaison est refusée en production.
     * La configuration lab.comparison_baseline_source_commit, non définie par
     * défaut (règle locale : ne pas toucher config/lab.php), peut la fournir dans
     * une release future sans modifier le code.
     */
    public function expectedBaselineSourceCommit(): ?string
    {
        $value = config('lab.comparison_baseline_source_commit');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function labIntegrity(): bool
    {
        return $this->lab->integrity();
    }
}

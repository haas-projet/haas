<?php

declare(strict_types=1);

namespace App\Data\VerificationCases;

/**
 * BV209 — Projection publique de la fiche de vérification d'une version
 * publiée (CAHIER_DES_CHARGES.md:1665 et 1673). Les blocs sont DISTINCTS et
 * jamais agrégés : la Resource les expose tels quels. Aucun score, aucun
 * badge, aucune réussite héritée d'une autre version. Jamais de reviewer_id,
 * note de revue, courriel, chemin privé d'artefact ni empreinte de notice.
 */
final class VerificationSummaryData
{
    /**
     * @param  list<array{handle:string,role:string}>  $contributors
     * @param  list<array{scenario_key:string,suite_digest:string,release_commit:string,integrated_at:string,reviewer_handle:string,source:string}>  $casesExamined
     * @param  list<array{handle:string,outcome:string,observation:string,environment:string,reported_at:string,is_internal:bool,source:string}>  $observationsDeclared
     */
    public function __construct(
        public readonly string $versionId,
        public readonly string $capsuleSlug,
        public readonly string $versionLabel,
        public readonly string $publishedAtIso,
        public readonly ?string $limits,
        public readonly array $contributors,
        public readonly array $casesExamined,
        public readonly array $observationsDeclared,
        public readonly VerificationSummaryLabEvidence $labEvidence,
    ) {}
}

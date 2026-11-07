<?php

declare(strict_types=1);

namespace App\Data\VerificationCases;

/**
 * BV209 — Bloc « tests exécutés et comparaisons effectuées » de la fiche.
 * Présent UNIQUEMENT quand il existe de vrais lab_results pour la version
 * (le worker B36/B37 n'est pas livré ; en production, les runs restent
 * queued). Sinon la Resource affiche « Aucun test de laboratoire disponible
 * pour cette version » via `message`.
 *
 * Les listes ne portent jamais assertions brutes, runtime, digest, env ni
 * seed : seuls un identifiant de run/comparaison, l'état et une source
 * stable sont exposés.
 */
final class VerificationSummaryLabEvidence
{
    /**
     * @param  list<array{id:string,scenario_key:string,state:string,finished_at:?string,source:string}>  $runs
     * @param  list<array{id:string,state:string,outcome:?string,finished_at:?string,source:string}>  $comparisons
     */
    private function __construct(
        public readonly bool $available,
        public readonly ?string $message,
        public readonly array $runs,
        public readonly array $comparisons,
    ) {}

    public static function unavailable(): self
    {
        return new self(
            available: false,
            message: 'Aucun test de laboratoire disponible pour cette version',
            runs: [],
            comparisons: [],
        );
    }

    /**
     * @param  list<array{id:string,scenario_key:string,state:string,finished_at:?string,source:string}>  $runs
     * @param  list<array{id:string,state:string,outcome:?string,finished_at:?string,source:string}>  $comparisons
     */
    public static function withEvidence(array $runs, array $comparisons): self
    {
        return new self(available: true, message: null, runs: $runs, comparisons: $comparisons);
    }
}

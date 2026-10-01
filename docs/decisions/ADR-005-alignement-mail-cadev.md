# ADR-005 — Communauté d’abord, atelier en complément

**Date :** 1er octobre 2026. **Statut :** adopté pour la conception à la demande de l’utilisateur, après transmission du mail de l’organisateur.

## Contexte
Le mail [M] insiste sur se retrouver, échanger, poser des questions, partager les connaissances, présenter les projets et collaborer. L’atelier seul ne rendait pas assez visibles la découverte et la présentation de projets. Le mail ne fournit pas de liste technique obligatoire.

## Décision
HAAS est présenté d’abord comme une communauté. Ajouter F16 (fiche projet avec demandes liées) et F17 (annuaire volontaire). Préciser F02 pour une question sans bug ni code obligatoire. Conserver F13–F15 comme parcours différenciant applicable à certaines solutions, pas comme passage obligé. Détails : docs/product/COMMUNAUTE_ET_PROJETS.md.

## Invariants
Nom HAAS ; deux développeurs ; Laravel/React ; backend complet puis GO_FRONTEND ; petits commits et revue croisée ; un VPS Systalink + Vercel ; Qodana Ultimate déclaré ; pas de code arbitraire. ADR-001 et ADR-004 restent applicables. Le discours centré exclusivement sur l’atelier d’ADR-002 est remplacé, mais ses mécanismes métier restent actifs.

## Conséquences
106 lots (94 conservés, 12 ajoutés), 68 AC (AC47 conditionnel), 20 familles UX. Étendre schéma, autorisations, OpenAPI, code frontend, tests et démo. Charge à réestimer. Le mail n’annule pas les arbitrages de date, droits, diffusion ou prix. Aucune fonctionnalité n’est déclarée implémentée par ce changement documentaire.

# BC01 — Réception du schéma projets, 11 octobre 2026

## Livraison et provenance

Branche isolée `backend/communaute-entraide-bc01-schema`, dérivée de B19 `39f96e94d8664bb39da4083a57fbd93ba295a643` (#38 sur B18 #37). Préparation autorisée pendant la revue des prérequis ; aucune fusion de PR distante. Identité Git existante conservée ; assistance Codex, aucun avis humain attribué à Lamine.

Migration additive projets/pivot, enums, modèles et factory fictive ; préférences privées avec opt-in false ; rattachement de demande nullable et FK composite garantissant son propriétaire. Retour transactionnel refusant toute donnée connue. Les anciens tests de rollback sont composés explicitement, sans CASCADE. [Contrat SQL et limites](../api/BC01_PROJECT_SCHEMA.md).

## Contrôles reçus sur le code figé

PHP 8.5.10, PostgreSQL 17 local, runner `node .worktrees/run-pr-review-20261011.cjs bc01`, base dédiée `haas_bc01_review_test` sur `127.0.0.1:55447` ; aucune base d'un autre lot ciblée. Vendor copié indépendamment au même composer.lock ; aucune dépendance ajoutée.

| Commande après le runner | Résultat |
| --- | --- |
| `vendor/bin/phpunit --testsuite Unit,Feature,Architecture --colors=never --log-junit storage/logs/bc01-nonsql-final.xml` | **530 tests / 5167 assertions**, erreurs/échecs/skips 0 |
| `vendor/bin/phpunit --testsuite Integration --filter 'ProjectsSchemaTest|ProjectsDiscoveryMigrationTest|IdentityMigrationTest|HelpRequestsMigrationTest' --colors=never --log-junit storage/logs/bc01-composed-green.xml` | **66 tests / 469 assertions**, erreurs/échecs/skips 0 |
| `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Niveau 8, aucune erreur |
| `vendor/bin/pint --test` | Réussi |
| `git diff --check` | Réussi |
| `vendor/bin/phpunit --testsuite Integration --colors=never --log-junit storage/logs/bc01-integration-final.xml` | **En cours à ce bilan ; aucun résultat complet reçu** |

Les deux suites reçues sont disjointes : 596 tests / 5636 assertions. Ce total ne comprend pas la suite Integration complète encore en cours.

## Contre-épreuves et corrections

Avant les modèles/enums : Unit 7/7 avec 7 échecs, puis 7/60 vert. Avant la migration : SQL 1/1 rouge, projects absente. Premier ciblage schema 57/392 vert. Composition antérieure : 7/39 avec cinq erreurs FK du cleanup Identity et un échec de colonne project_id après remontée. Les seuls helpers concernés sont ajustés dans l'ordre enfants/parents et descente/remontée explicite BC01 ; réception finale 66/469 verte. Deux corrections Pint et neuf types/nullabilités dans les tests ont été corrigés sans désactiver de règle ; PHPStan final vert.

## État et suite

BC01 **IN_PROGRESS** : contrôles ciblés reçus, suite SQL complète et CI exacte à recevoir, puis revue humaine et intégration. Les preuves de schéma couvrent des parties de AC53/AC60 ; elles ne reçoivent ni les commandes projets ni l'annuaire. BC02–BC06 et BH01–BH10 restent à implémenter dans leurs lots. Aucun BACKEND_GATE, GO_FRONTEND ou GO_PRODUCTION.

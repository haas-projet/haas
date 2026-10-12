# BC02 — Commandes projets, 12 octobre 2026

## Périmètre et provenance

Branche isolée `backend/communaute-entraide-bc02-brouillons` depuis BC01 `77910ba9ed2dc94985498c91f703cb68766f0060` (#39 ready après CI finale38194657898, 1221/12660 par PHP). Préparation autorisée pendant la revue des prérequis. Identité Git existante conservée, aucune validation humaine attribuée à Lamine.

Deux routes POST /api/v1/projects et PATCH /api/v1/projects/{id} : FormRequests → DTO → Services → Resource, Policy pour les droits, Query pour l'accès, socle IdempotencyService et audit dans content_revisions. Brouillon privé et incomplet autorisé ; propriétaire/session serveur, phase déclarée distincte de la publication. Liens HTTPS inertes. Un patch ne publie pas, ni ne transfère le propriétaire, et valide la fiche publiée composée avant mutation. [Contrat](../api/PROJECTS.md).

## Réception locale figée

PHP8.5.10, runner `node .worktrees/run-pr-review-20261011.cjs bc02`, base exclusive `haas_bc02_review_test` sur PostgreSQL17 `127.0.0.1:55447`. Vendor indépendant au même composer.lock, aucune dépendance ajoutée.

| Commande après le runner | Résultat |
| --- | --- |
| `vendor/bin/phpunit --testsuite Unit,Feature,Architecture --colors=never --log-junit storage/logs/bc02-nonsql-received.xml` | **558 tests / 5276 assertions**, erreurs/échecs/skips0 |
| `vendor/bin/phpunit --testsuite Integration --filter 'ProjectCommandsTest|ProjectConcurrencyTest' --colors=never --log-junit storage/logs/bc02-commands-received.xml` | **42 / 355**, erreurs/échecs/skips0, dont cinq courses à deux vrais processus |
| `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Niveau8, aucune erreur |
| `vendor/bin/pint --test` | Réussi |
| `../scripts/generate-api-types.php --check` | **69 types à jour** |
| `vendor/bin/phpunit --testsuite Integration --colors=never --log-junit storage/logs/bc02-integration-final.xml` | En cours au bilan ; aucun résultat complet reçu |

Les deux suites reçues sont disjointes : **600 tests / 5631 assertions**. Les 42 ciblés ne seront pas additionnés à la future suite SQL complète. HTTP réel par kernel/cookies/CSRF ; droits, propriété, admin non propriétaire, champ protégé, JSON brut, Unicode, références, conflit/rejeu et rollback testés. Les courses vérifient les verrous PostgreSQL réels avant leurs relâchements : création doublée de la même intention, deux éditions distinctes, rejeu partagé, suspension et masquage pendant attente. Aucun deadlock observé. Les assertions de polling ne dépendent pas du nombre d'itérations.

## Incidents reçus et corrigés

Premier ciblage36/270 avec deux échecs : objet JSON technology_ids restauré comme stdClass →500 ; fixture float1.0 sérialisée en1, acceptée légitimement. Décodage associatif contrôlé avec garde objet/liste ajouté : {} n'est pas []. La fixture fractionnaire utilise1.5 ; une contre-épreuve JSON brut1.0 contient la clé d'idempotence valide et vérifie spécifiquement l'erreur lock_version. HTTP37/279 puis ciblage final42/355 verts.

Première analyse statique interrompue par une course Windows au premier bootstrap packages.php ; package:discover réchauffe uniquement le cache ignoré du worktree. Quatre types métier/fixture et deux types de concurrence corrigés sans baseline/suppression ; PHPStan final vert. Première suite générale558/5201 échouait sur ApiInventoryTest : les deux nouvelles routes n'étaient pas encore référencées dans OPENAPI.yaml. Le raccord racine complète ce contrat, sans affaiblir le test ; réception558/5276 verte. Pint a appliqué uniquement cinq corrections de style dans les fichiers du lot.

## État et suite

BC02 **IN_PROGRESS**, ciblés/statique reçus ; suite SQL complète et CI exacte à recevoir avant ready, puis revue humaine/intégration. Prérequis #37 → #38 → #39, puis cette branche. BC03 publication/catalogue/archivage, BC04 liens de demandes et BC05 modération restent leurs lots distincts. Aucun BACKEND_GATE, frontend ou déploiement.

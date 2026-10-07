# HAAS — Registre d’assistance IA

| Date | Outil / usage | Périmètre | Vérification humaine | Tests |
|---|---|---|---|---|
| 30/09/2026 | ChatGPT — préparation du pack d’instructions | Plan Codex, skills HAAS, spécifications et script de contraste | À relire par l’équipe | Vérification documentaire et script consignés dans le rapport de validation de la version concernée ; aucun test de l’application |

Pour le code futur : outil effectivement utilisé, date, lot, fichiers, relecteur réel, tests réellement exécutés et limites. Ne pas qualifier automatiquement une sortie d’IA de libre de droits ou de sûre.

## Mise à jour documentaire du 1er octobre 2026

| Outil / intervention | Périmètre | Contrôles réalisés | Revue humaine de l’équipe |
|---|---|---|---|
| ChatGPT — préparation de l’atelier | Cahier consolidé, contrat atelier, plan Codex, skill supplémentaire, slides et notes | Contrôles documentaires et rendus décrits dans le rapport de validation de la version concernée ; aucun test applicatif | À réaliser |
| Logo déjà généré dans la conversation | PNG fourni, recadrage et déclinaisons raster/icônes | Formats, tailles et canal alpha contrôlés | À réaliser ; disponibilité de marque non vérifiée |

Ces entrées ne décrivent pas une utilisation effective de Codex pour produire une application ni une approbation humaine qui aurait eu lieu.

## Consolidation du déploiement — 1er octobre 2026

| Outil / intervention | Périmètre | Contrôles | Revue humaine de l’équipe |
|---|---|---|---|
| ChatGPT — consolidation du déploiement | Choix Systalink + Vercel, cahier PDF, brief Codex, modèles non exécutés, présentation | Contrôles documentaires, calculs de contraste et inspection des rendus, consignés dans `docs/quality/PACK_VALIDATION.md` | À réaliser |

Aucun code du produit HAAS n’a été développé ni déployé lors de cette finalisation. Les scripts inclus vérifient des documents et des couleurs, pas le fonctionnement d’une application.


## Mise à jour documentaire selon le mail
Assistant utilisé pour consolider les exigences, instructions Codex, cahier et présentation. Aucun nouveau code applicatif déployé ; les scripts de génération/validation du dossier ne prouvent pas les tests du produit. Consigner séparément chaque usage IA pendant l’implémentation.

## Mise à jour documentaire Coup de main

Outil : assistant conversationnel, 1er octobre 2026. Tâche : intégrer la décision F18 au pack, sources Markdown, contrats de conception, tâches, critères, notes et livrables embarqués. Les scripts documentaires/contraste/rendu exécutés sont consignés dans docs/quality. Aucun développement Laravel/React, commit ou test d’usage réel n’est attesté ; relecture de l’équipe requise avant exécution. Aucune nouvelle recherche web ou validation de tarifs dans ce lot documentaire.

## Lot 1 capsules/laboratoire — 2 octobre 2026

| Outil / intervention | Périmètre | Contrôles | Revue humaine de l'équipe |
|---|---|---|---|
| Claude Code (Opus 4.7, `claude-opus-4-7`), participant `mdev44-code` sur `backend/capsules-laboratoire` | Enums `App\Enums\Capsules\CapsuleVersionState` (avec `canTransitionTo` et `allowedTargets`), `App\Enums\Lab\LabRunState`, `App\Enums\Comparisons\{ComparisonState,ComparisonOutcome}` et leurs tests unitaires ; mise à jour `docs/execution/participants/mdev44-code/{PROGRESS,HANDOFF}.md` | `composer lint` passed, `composer analyse` niveau 8 `[OK] No errors`, `composer test` 148 tests / 977 assertions OK, `composer test:integration` 35 tests / 296 assertions OK sous PostgreSQL 17, `composer audit --locked` sans alerte, `composer validate --strict --no-check-publish` valide | En attente |

Les transitions `in_review → published` et `published → withdrawn` sont déduites (B25, B31, `docs/product/CAHIER_DES_CHARGES.md:502`, AC15) et signalées « à confirmer par le relecteur » dans le test. Aucun commit poussé, aucune PR ouverte, aucune fusion effectuée.

## Lot B11 schéma collaboration — 3 octobre 2026

| Outil / intervention | Périmètre | Contrôles | Revue humaine de l'équipe |
|---|---|---|---|
| Claude Code (Opus 4.7, `claude-opus-4-7`), participant `mdev44-code` sur `backend/communaute-entraide-b11-schema` (cession LamineGL du 2026-10-03) | Enums `App\Enums\HelpRequests\HelpRequestState`, `App\Enums\Collaboration\ProposalState` ; 5 migrations convention Laravel (`create_help_requests_table`, `create_request_technologies_table`, `create_proposals_table`, `create_comments_table`, `create_resolutions_table`) avec contraintes et index unique partiel ; modèles `HelpRequest`, `Proposal`, `Comment`, `Resolution` ; factories associées ; tests unitaires d'enums + `HelpRequestsSchemaTest` (6 cas PostgreSQL) ; mise à jour `IdentityMigrationTest` pour drop des tables consommatrices avant `B05 down()` ; `docs/execution/participants/mdev44-code/{PROGRESS,HANDOFF}.md` | `composer lint` passed, `composer analyse` niveau 8 `[OK] No errors`, `composer test` 122 tests / 926 assertions OK, `composer test:integration` 41 tests / 310 assertions OK sous PostgreSQL 17 sur `haas_capsules_test`, `composer audit --locked` sans alerte, `composer validate --strict --no-check-publish` valide | En attente |

Écart assumé à `docs/execution/BACKEND_A_TROIS.md:110` sur le nommage des migrations (convention Laravel sans identifiant de lot ; décision de mdev44-code à discuter avec le responsable 1 ; renommage trivial avant merge si refusé). Aucune colonne `code`/`code_language` ajoutée faute de nom de colonne cité dans le dépôt. Aucun commit poussé, aucune PR ouverte, aucune fusion effectuée.

## Lot B11 — fusion de main et corrections — 3 octobre 2026

| Outil / intervention | Périmètre | Contrôles | Revue humaine de l'équipe |
|---|---|---|---|
| Claude Code (Opus 4.7, `claude-opus-4-7`), participant `mdev44-code` sur `backend/communaute-entraide-b11-schema` | Fusion `git merge origin/main` sans conflit (commit `b152fc1`, main avancé de `075e6eb` à `a051e81` : B07–B13 et B29/B30/B32 intégrés par `ousseynoufayeisidk-sys`) ; retrait des 10 CHECK de longueur (incompatibles avec `"aucune"` explicite pour `attempts`, CAHIER:381, et avec les règles conditionnelles de `ask_question`, CAHIER:408) ; correction du commentaire d'en-tête de `create_help_requests_table` (référence erronée remplacée par CAHIER:394) ; retrait de `state` des `#[Fillable]` ; `ResolutionFactory` sans `create()` dans `definition()` ; simplification des factories et des tests enums ; mise à jour `docs/execution/participants/mdev44-code/{PROGRESS,HANDOFF}.md` | `composer lint` passed, `composer analyse` niveau 8 `[OK] No errors`, `composer test` 253 tests / 2454 assertions OK, `composer test:integration` 141 tests / 1266 assertions OK sous PostgreSQL 17 sur `haas_capsules_test`, `composer audit --locked` sans alerte, `composer validate --strict --no-check-publish` valide | En attente |

Aucun commit poussé, aucune PR ouverte, aucune fusion effectuée.

## Reprise B11 sur la branche permanente de Lamine — 3 octobre 2026

Codex, à la demande de l'utilisateur : reprise sans réécriture du travail B11 de mdev44-code, compléments de versions positives/protection des champs serveur/factory, tests de FK et migrations, contrat et suivi. Aucune identité Git de collègue ni revue humaine simulée. Vérifications : Pint/PHPStan, 282 tests / 2483 assertions hors SQL, 151 tests / 1316 assertions PostgreSQL ; B11 ciblé 18 / 66 après corrections ; audit sans alerte et validation Composer. Preuves et limites dans `docs/quality/B11_COLLABORATION.md`. La CI et le SHA final seront rapportés dans la PR ; aucun déploiement ni frontend.

## B14 — Création des demandes, 4 octobre 2026

| Outil / intervention | Périmètre | Contrôles | Revue humaine de l'équipe |
|---|---|---|---|
| Codex, suite de Lamine à la demande de l'utilisateur, après confirmation que Madina n'a pas commencé B14/HelpIntent | Endpoint de création, validation, DTO, Policy, service, Resource, enum partagé unique, migration additive, audit/idempotence, contrats et tests ; B11 et suivi de Madina conservés | Pint/PHPStan ; 284 tests / 2686 assertions et 192 tests PostgreSQL / 1732 assertions ; audit sans alerte, validation Composer, 36 types API à jour. Voir B14_CREATION.md ; CI finale à observer dans la PR | En attente ; aucune signature humaine simulée |


## B15 — Lecture et recherche, 4 octobre 2026

Codex, à la demande de continuer la partie de Lamine : Queries de visibilité, liste/détail, Request/DTO, collection paginée, contrat et tests. Travail B11 de Madina et PR #12 conservés ; modification locale des routes capsules préservée dans le répertoire principal, livraison isolée dans `.worktrees/b15`. 511 tests / 4834 assertions réussis (dont 227 / 2023 PostgreSQL), Pint/PHPStan, validation/prérequis/audit Composer et documentation verts. Preuves et limites dans B15_READING.md. Revue humaine en attente ; aucune identité ni validation d'un collègue simulée. CI du SHA final à observer dans la PR.

## B16 — Édition, publication et historique, 4 octobre 2026

Codex, à la demande de continuer la partie de Lamine : FormRequests/DTO/Policy/Service/Queries/Resources, migration additive, validation B14 partagée, audit/idempotence, contrat et tests. Branches/PR de Madina et fichier capsules local préservés. Tests HTTP, droits et versions, rollback et six courses PostgreSQL réelles ; commandes et résultats exacts dans `docs/quality/B16_EDITING.md`. Corrections des tests de retour B05/B11 pour respecter la nouvelle clé étrangère. Revue humaine en attente ; aucune identité ni validation d'un collègue simulée. SHA final et CI à observer dans la PR. Aucun frontend ni déploiement.

## Lot 2 B35 brique B1 — 5 octobre 2026

| Outil / intervention | Périmètre | Contrôles | Revue humaine de l'équipe |
|---|---|---|---|
| Claude Code (Opus 4.7, `claude-opus-4-7`), participant `mdev44-code` sur `backend/capsules-laboratoire-b35-brique-b1` (dérivée de `origin/backend/capsules-laboratoire`) | B35 brique B1 : migrations `create_test_events_table`/`create_test_orders_table`, modèles `App\Models\Lab\{LabConnection,TestEvent,TestOrder}` (point de bascule applicatif vers `haas_lab` via `LabConnection::NAME`), factories jumelles, DTO `App\Data\Lab\{TestEventData,ProcessedTestEvent}`, service transactionnel `App\Services\Lab\ProcessTestEventService::handle` ciblant `DB::connection(LabConnection::NAME)->transaction(...)` et renvoyant un résultat typé `{order, duplicate}` avec validation stricte (regex `D`-ancrées) et idempotence via `insertOrIgnore` sur `(run_id, event_id)` ; tests Integration schéma, service B1-01..04 avec cas atomicité et rejeu divergent, concurrence réelle B1-05 pompée simultanément et chevauchement forcé par un trigger `pg_sleep(0.3)` posé via la connexion secondaire ; mise à jour `docs/execution/participants/mdev44-code/{PROGRESS,HANDOFF}.md` et `docs/AI_USAGE.md` | `composer lint` passed, `composer analyse` niveau 8 `[OK] No errors`, `composer test` 148 tests / 977 assertions OK, `composer test:integration` 59 tests / 417 assertions OK sous PostgreSQL 17 / `haas_capsules_test` / Laragon PHP 8.4.15 | En attente |

Décisions explicites : migration sans préfixe de lot — dérogation assumée à `docs/execution/BACKEND_A_TROIS.md:110` (« une migration par table, nom descriptif, sans préfixe de lot »), à arbitrer par le relecteur ; `run_id` reste un identifiant logique sans FK vers `lab_runs` (bases `haas_app` et `haas_lab` distinctes, aucune FK inter-bases possible) ; aucune route HTTP exposée (consommation par le worker B36 à venir). Écart ouvert : les tables sont pour l'instant sur la connexion par défaut parce que `LabConnection::NAME = null` ; le câblage de `haas_lab` touchera `App\Models\Lab\LabConnection::NAME` côté domaine, `backend/config/database.php`, `backend/phpunit.xml` et la CI côté responsable 1, puis la connexion secondaire de nettoyage et la configuration `RefreshDatabase` côté tests (demande à soumettre à `ousseynoufayeisidk-sys` pour la part socle). PHP par défaut `C:\Program Files\php\php.exe` 8.4.3 n'enregistre ni `openssl`, ni `pdo_pgsql`, ni le transport `unix://` ; `C:\laragon\bin\php\php-8.4.15-Win32-vs17-x64\php.exe` corrige les deux premiers mais pas le troisième — l'étape B36 devra passer par la CI Linux `ubuntu-24.04` ou WSL2.

## B17 — Commentaires historisés, 7 octobre 2026

Codex, suite demandée de Lamine et publication : reprise de l'implémentation B17 non commitée existante, revue des droits/verrous/idempotence/Markdown, correction de fixtures et typage réel, ajout de tests de concurrence, migration et notifications ; contrat OpenAPI/types et suivi. Historiques B14/B15/B16 et B35 conservés lors du seul conflit documentaire de main. 671 tests / 6805 assertions distincts, Pint/PHPStan et contrôles Composer/documentaires réellement exécutés ; commandes dans B17_COMMENTS.md. Aucune nouvelle dépendance, aucune identité Git de collègue ou signature humaine inventée. CI du SHA final en attente d'observation après publication. Aucun frontend, Qodana ou déploiement annoncé.

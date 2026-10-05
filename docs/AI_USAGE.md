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

## Lot 2 B35 brique B1 — 5 octobre 2026

| Outil / intervention | Périmètre | Contrôles | Revue humaine de l'équipe |
|---|---|---|---|
| Claude Code (Opus 4.7, `claude-opus-4-7`), participant `mdev44-code` sur `backend/capsules-laboratoire-b35-brique-b1` (dérivée de `origin/backend/capsules-laboratoire`) | B35 brique B1 : migrations `create_test_events_table`/`create_test_orders_table`, modèles `App\Models\Lab\{LabConnection,TestEvent,TestOrder}` (point de bascule applicatif vers `haas_lab` via `LabConnection::NAME`), factories jumelles, DTO `App\Data\Lab\{TestEventData,ProcessedTestEvent}`, service transactionnel `App\Services\Lab\ProcessTestEventService::handle` ciblant `DB::connection(LabConnection::NAME)->transaction(...)` et renvoyant un résultat typé `{order, duplicate}` avec validation stricte (regex `D`-ancrées) et idempotence via `insertOrIgnore` sur `(run_id, event_id)` ; tests Integration schéma, service B1-01..04 avec cas atomicité et rejeu divergent, concurrence réelle B1-05 pompée simultanément et chevauchement forcé par un trigger `pg_sleep(0.3)` posé via la connexion secondaire ; mise à jour `docs/execution/participants/mdev44-code/{PROGRESS,HANDOFF}.md` et `docs/AI_USAGE.md` | `composer lint` passed, `composer analyse` niveau 8 `[OK] No errors`, `composer test` 148 tests / 977 assertions OK, `composer test:integration` 59 tests / 417 assertions OK sous PostgreSQL 17 / `haas_capsules_test` / Laragon PHP 8.4.15 | En attente |

Décisions explicites : migration sans préfixe de lot — dérogation assumée à `docs/execution/BACKEND_A_TROIS.md:110` (« une migration par table, nom descriptif, sans préfixe de lot »), à arbitrer par le relecteur ; `run_id` reste un identifiant logique sans FK vers `lab_runs` (bases `haas_app` et `haas_lab` distinctes, aucune FK inter-bases possible) ; aucune route HTTP exposée (consommation par le worker B36 à venir). Écart ouvert : les tables sont pour l'instant sur la connexion par défaut parce que `LabConnection::NAME = null` ; le câblage de `haas_lab` touchera `App\Models\Lab\LabConnection::NAME` côté domaine, `backend/config/database.php`, `backend/phpunit.xml` et la CI côté responsable 1, puis la connexion secondaire de nettoyage et la configuration `RefreshDatabase` côté tests (demande à soumettre à `ousseynoufayeisidk-sys` pour la part socle). PHP par défaut `C:\Program Files\php\php.exe` 8.4.3 n'enregistre ni `openssl`, ni `pdo_pgsql`, ni le transport `unix://` ; `C:\laragon\bin\php\php-8.4.15-Win32-vs17-x64\php.exe` corrige les deux premiers mais pas le troisième — l'étape B36 devra passer par la CI Linux `ubuntu-24.04` ou WSL2.

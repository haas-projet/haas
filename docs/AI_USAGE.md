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

## Préparation de la PR #30 B23 — 7 octobre 2026

Codex, à la demande de l'utilisateur : audit du travail `93fafea`, probes de défauts puis corrections de provenance, concurrence, validation, édition et idempotence sans modifier les décisions Q8/Q9. Contrôles locaux : 13 tests / 111 assertions PostgreSQL (six courses réelles), 323 tests / 2675 assertions hors SQL, Pint passé, PHPStan sans erreur, 18 + 7 contrôles documentaires, 33 types API à jour. Preuves, essais échoués et limites dans `docs/quality/B23_DRAFT_READINESS.md`. Synchronisation B22/main, suite combinée complète, CI distante et revue humaine en attente. Aucune validation humaine simulée ; aucun frontend ou déploiement.

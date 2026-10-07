# Progression — capsules/laboratoire

## 2026-10-02 — Prise en main et phase 0

Première session sur `backend/capsules-laboratoire`. Inspection du dépôt, des 23 lots pilotés (B22–B28, B33–B38, BV201–BV210) et des documents de référence : `backend/AGENTS.md`, `docs/architecture/ARCHITECTURE.md`, `docs/api/HTTP_CONTRACT.md`, `docs/execution/BACKEND_A_TROIS.md`, `docs/product/{ATELIER_COLLABORATIF,CAHIER_DES_CHARGES}.md` et les PROGRESS/HANDOFF de `ousseynoufayeisidk-sys`. Aucun code métier capsules/lab livré avant cette session.

Synchronisation avec `main` : `git status` propre avant début ; `git fetch origin` ramène 15 commits de `origin/main` (B02–B06 intégrés par le responsable 1) ; `git merge --no-ff origin/main` sans conflit, commit de merge local `5e8fc46`, non poussé. 86 fichiers modifiés par le merge, dont `app/Models/{Profile,Technology,User}.php`, `app/Enums/Identity/*`, `app/Support/Http/{ApiExceptionRenderer,RequestId,AssignRequestId}.php`, `app/Data/Common/PageData.php`, `app/Http/Requests/PaginatedRequest.php`, `app/Http/Resources/PaginatedResourceCollection.php`, migrations `2026_10_02_000005_b05_create_identity_and_reference_tables.php` et `2026_10_02_000006_b06_create_user_terms_acceptances.php`, workflow `.github/workflows/backend-ci.yml`, et fragments `docs/api/openapi/{identity,community,capsules-lab}.yaml`.

Environnement local sélectionné pour la session : PHP 8.4.15 natif de Laragon (`C:\laragon\bin\php\php-8.4.15-Win32-vs17-x64\php.exe`, openssl + pdo_pgsql vérifiés). Le PHP du PATH par défaut `C:\Program Files\php\php.exe` 8.4.3 n'a pas `openssl`, Composer refuse l'installation : écart signalé mais sans contournement global. Le PATH global du poste n'est pas modifié ; la bascule est faite dans la session courante.

Base PostgreSQL de test dédiée provisionnée par l'utilisateur : rôle `haas_test` et base `haas_capsules_test` sur le cluster PG 17 (`127.0.0.1:5432`), mot de passe fourni hors historique dans `~/.haas-test.env` (fichier hors dépôt, jamais lu ni affiché par la session). Chaque appel aux tests d'intégration charge ce fichier dans la même commande via `set -a; source ~/.haas-test.env; set +a; composer test:integration`.

Commandes exactes exécutées et résultats réels :

| Commande | Résultat observé |
|---|---|
| `git status` | `working tree clean` sur `backend/capsules-laboratoire` aligné avec son origine |
| `git log --oneline HEAD..origin/main` | 15 commits à récupérer (B02 b665a78 → B06 075e6eb) |
| `git merge --no-ff origin/main` | Merge `ort` sans conflit, +3383 / −73 lignes, commit `5e8fc46` non poussé |
| `composer install --no-interaction --no-progress` | Dépendances verrouillées installées sous PHP 8.4.15, autoload et `package:discover` réussis |
| `composer test` | **118 tests / 922 assertions, OK** sous PHP 8.4.15 en 3,85 s (Unit + Feature + Architecture) |
| `composer test:integration` | **35 tests / 296 assertions, OK** sous PHP 8.4.15, PostgreSQL 17 sur `haas_capsules_test`, en 21,272 s ; `TestDatabaseGuard` accepté sans contournement |

Limites : aucun lot B22–BV210 commencé pendant la phase 0 ; aucun fichier interdit touché (`composer.json`, `composer.lock`, `bootstrap/`, `config/`, `.env.example`, `phpunit.xml`, `routes/api.php`, `.github/workflows/`). Le merge local avec `origin/main` reste en working tree, non poussé. Qodana et accès hébergement toujours non vérifiés. Les PR B05 (#8) et B06 (#9) sont déjà intégrées côté `origin/main` : les prérequis d'identité et d'inscription sont utilisables en lecture par la préparation indépendante de mon domaine, mais les routes métier n'existent pas encore.

## 2026-10-02 — Lot 1 enums capsules, lab et comparaisons

Petit lot de valeurs de domaine sans dépendance HTTP ni SQL. Portée restreinte sur demande du relecteur à `CapsuleVersionState`, `LabRunState`, `ComparisonState`, `ComparisonOutcome`. `ArtifactDistributionStatus` (ARB05 non résolu) et `CapsuleOrigin` (vocabulaire éditorial non tranché) sont renvoyés aux lots qui les consomment. Les DTO sont livrés avec les Services correspondants.

Transitions de `CapsuleVersionState` posées sur l'enum lui-même (`canTransitionTo()` et `allowedTargets(): list<self>`). Trois transitions citées littéralement par le dépôt sont implémentées : `draft → in_review`, `in_review → changes_requested`, `changes_requested → in_review` (B24, `docs/execution/PLAN_COMMITS.md:263`). Deux transitions déduites et marquées « à confirmer par le relecteur » dans le test : `in_review → published` (B25, `docs/execution/PLAN_COMMITS.md:273`) et `published → withdrawn` (B31, `docs/product/CAHIER_DES_CHARGES.md:502`, `docs/quality/ACCEPTANCE_MATRIX.md:21`). Les retraits depuis `draft`, `in_review` et `changes_requested` ne sont pas inscrits dans l'enum ; ils seront définis par les Services du lot B31.

Fichiers créés dans ce lot :

- `backend/app/Enums/Capsules/CapsuleVersionState.php`
- `backend/app/Enums/Lab/LabRunState.php`
- `backend/app/Enums/Comparisons/ComparisonState.php`
- `backend/app/Enums/Comparisons/ComparisonOutcome.php`
- `backend/tests/Unit/Enums/Capsules/CapsuleVersionStateTest.php`
- `backend/tests/Unit/Enums/Lab/LabRunStateTest.php`
- `backend/tests/Unit/Enums/Comparisons/ComparisonStateTest.php`
- `backend/tests/Unit/Enums/Comparisons/ComparisonOutcomeTest.php`

Commits locaux (branche `backend/capsules-laboratoire`, non poussés) :

1. `db30cf6` `feat(capsules): définir l'état des versions et les transitions documentées`
2. `db1ed8d` `feat(lab): définir l'état des exécutions de laboratoire`
3. `14c4579` `feat(comparisons): définir l'état et les conclusions des comparaisons`
4. commit documentaire courant.

Contrôles exécutés localement sous PHP 8.4.15 :

| Commande | Résultat observé |
|---|---|
| `composer lint` (Pint `--test`) | `{"tool":"pint","result":"passed"}` |
| `composer analyse` (PHPStan niveau 8) | `[OK] No errors` |
| `composer test` | **148 tests / 977 assertions, OK** en 3,900 s (+30 tests, +55 assertions par rapport à `origin/main`) |
| `composer test:integration` | **35 tests / 296 assertions, OK** en 15,361 s ; aucune migration ajoutée par ce lot |
| `composer audit --locked --no-interaction` | `No security vulnerability advisories found.` |
| `composer validate --strict --no-check-publish` | `./composer.json is valid` |

Limites : les transitions `in_review → published` et `published → withdrawn` sont inscrites comme déduites ; la décision formelle appartient au relecteur. Aucun DTO, migration, route ou service livré. Aucun fichier interdit touché. Aucun push, merge, PR ouverte ou gate déclaré. CI distante non observée (aucune PR ouverte à ce stade).

Prochaine action : attendre le feu vert du relecteur pour pousser la branche et ouvrir une PR vers `main` avec `Refs #3`. Ensuite, démarrer sur une branche dérivée séparée les classes pures 100 % indépendantes des prérequis manquants : `ComparisonOutcomeCalculator` (BV208) et scénarios B1 fictifs purs (B35, partie unitaire). Les lots qui dépendent de B07 (session), B09 (droits), B11 (collaboration), B12 (audit) ou B13 (idempotence) attendront leurs prérequis.

## 2026-10-03 — Lot B11 schéma collaboration (cession LamineGL)

Cession actée par l'utilisateur le 2026-10-03 : `LamineGL` me cède ce qui est lié à capsules, laboratoire et atelier, y compris B11. Pour l'instant je ne reprends **que** B11 ; B14/B16/B20 ont une citation de rattachement (`tasks.json:180` et `PLAN_COMMITS.md:163` pour B14/B16 via « Atelier : intégrer help_intent » ; `tasks.json:246` et `PLAN_COMMITS.md:223` pour B20 via « marquer les capsules liées à revoir ») mais ne sont pas démarrés. Le reste (B15, B17, B18, B19, B21, BC01–BC08, BH01–BH10) reste à `LamineGL`.

Branche créée depuis `origin/main` : `backend/communaute-entraide-b11-schema`, base `075e6eb`. Branche parente `backend/capsules-laboratoire` **non intégrée** (lots B22+ en attente de l'ouverture de PR #12). CLAUDE.md local reçoit la règle « migrations convention Laravel, une par table, sans numéro de lot » (écart assumé à `BACKEND_A_TROIS.md:110`).

Fichiers livrés sur `backend/communaute-entraide-b11-schema` :

- `backend/app/Enums/HelpRequests/HelpRequestState.php` (valeurs citées `ARCHITECTURE.md:255`, sans transition).
- `backend/app/Enums/Collaboration/ProposalState.php` (valeurs citées `ARCHITECTURE.md:256`, sans transition).
- `backend/database/migrations/2026_10_03_180000_create_help_requests_table.php` (colonnes `CAHIER_DES_CHARGES.md:825`, longueurs `CAHIER:378-384`, défaut `state=draft`, `expected`/`attempts` NOT NULL en B11, `environment` string sans limite citée, CHECK constraints `23514`).
- `backend/database/migrations/2026_10_03_180100_create_request_technologies_table.php` (clé primaire composite `(request_id, technology_id)` alignée sur `user_technologies` de B05 ; `version_label` 40 cité `CAHIER:382`).
- `backend/database/migrations/2026_10_03_180200_create_proposals_table.php` (colonnes `CAHIER:828`, longueurs 20–4 000 `CAHIER:418`, défaut `state=proposed`, UNIQUE `(request_id, id)` prérequis de la FK composite).
- `backend/database/migrations/2026_10_03_180300_create_comments_table.php` (colonnes `CAHIER:827`, longueur 1–4 000 `CAHIER:418`).
- `backend/database/migrations/2026_10_03_180400_create_resolutions_table.php` (colonnes `CAHIER:829`, FK composite `(request_id, proposal_id) → proposals (request_id, id)` demandée par `tasks.json:147` + `PLAN_COMMITS.md:133`, index unique partiel `resolutions_one_active_per_request` `ARCHITECTURE.md:316-320`).
- `backend/app/Models/{HelpRequest,Proposal,Comment,Resolution}.php` (relations et casts enums).
- `backend/database/factories/{HelpRequest,Proposal,Comment,Resolution}Factory.php`.
- `backend/tests/Unit/Enums/HelpRequests/HelpRequestStateTest.php`, `backend/tests/Unit/Enums/Collaboration/ProposalStateTest.php`.
- `backend/tests/Integration/HelpRequests/HelpRequestsSchemaTest.php` (6 tests PostgreSQL : graphe valide avec défauts, doublon de pivot refusé 23505, FK composite refusée 23503, unicité partielle de résolution active 23505, slot libéré après `revoked_at`, CHECK d'état et longueur 23514).
- `backend/tests/Integration/IdentityMigrationTest.php` : ajout du nettoyage des tables consommatrices (`resolutions, comments, proposals, request_technologies, help_requests`) avant `runIdentityMigration('down')`, pour que PostgreSQL puisse déposer `technologies`. Modification cross-domain couverte par la cession LamineGL ; `LamineGL` reste informé.

Pas de colonne `code`/`code_language` ajoutée : l'exigence 12 000 caractères est citée (`CAHIER:383`, `CAHIER:418`, `tasks.json:224`) mais aucun nom de colonne SQL n'est cité dans le dépôt ; non codée en B11.

Convention de nommage des migrations : convention Laravel, une par table, sans numéro de lot. **Écart assumé à `BACKEND_A_TROIS.md:110`** (« nom unique avec identifiant du lot »). Décision de mdev44-code, à discuter avec le responsable 1 ; renommage trivial avant merge si refusé. Noté dans CLAUDE.md local.

Contrôles exécutés localement sous PHP 8.4.15 :

| Commande | Résultat observé |
|---|---|
| `composer lint` (Pint `--test`) | `{"tool":"pint","result":"passed"}` |
| `composer analyse` (PHPStan niveau 8) | `[OK] No errors` |
| `composer test` | **122 tests / 926 assertions, OK** en 1,98 s (+4 tests enums par rapport à `origin/main`) |
| `composer test:integration` | **41 tests / 310 assertions, OK** en 11,034 s sous PostgreSQL 17 sur `haas_capsules_test` (+6 tests schéma vs `origin/main`) |
| `composer audit --locked --no-interaction` | `No security vulnerability advisories found.` |
| `composer validate --strict --no-check-publish` | `./composer.json is valid` |

Limites : aucun DTO/FormRequest/Service/Controller/Resource/Policy/route livré en B11 ; ces couches sont portées par B14–B21. Aucun fichier interdit touché (`composer.json`, `composer.lock`, `bootstrap/`, `config/`, `.env.example`, `phpunit.xml`, `routes/api.php`, `.github/workflows/`). Fragment `docs/api/openapi/community.yaml` inchangé. Aucun push, PR ouverte ou merge. Revue humaine `LamineGL` ou `ousseynoufayeisidk-sys` en attente.

Prochaine action : attendre le feu vert pour pousser `backend/communaute-entraide-b11-schema` et ouvrir une PR vers `main` avec `Refs #2` et la mention « Responsables consultés : LamineGL (accord de LamineGL du 2026-10-03) ».

## 2026-10-03 — Fusion de main et corrections post-revue (B11)

Fusion `git merge origin/main` sans conflit (commit `b152fc1`). `origin/main` a avancé de `075e6eb` à `a051e81` entre-temps : B07–B13 et B29/B30/B32 fusionnés par `ousseynoufayeisidk-sys`. Dépendances B07–B13 désormais disponibles. `IdentityMigrationTest.php` n'a pas été modifié côté main depuis B05 : mon ajout reste la seule différence sur ce fichier.

Corrections appliquées sur demande de l'utilisateur (commit `12c0d50`) :

- **Retrait des 10 CHECK de longueur** (help_requests ×5, proposals ×4, comments ×1). Motif cité : `CAHIER_DES_CHARGES.md:381` autorise explicitement `"aucune"` (6 caractères) pour `attempts`, incompatible avec un CHECK ≥ 20. Les règles conditionnelles de `help_intent=ask_question` (CAHIER:408) justifient aussi un report en FormRequest B14+/B17+/B18+. `title varchar(140)` et `version_label varchar(40)` conservés (strictement cités) ; CHECK d'énumération d'état conservés.
- **Correction du commentaire d'en-tête `create_help_requests_table`** : la référence erronée à `users_technologies.technology_id->users` est remplacée par « RESTRICT par défaut : aucune suppression de demande dans le produit (CAHIER:394) ».
- **Retrait de `state` des `#[Fillable]`** des modèles `HelpRequest` et `Proposal` : la transition d'état passe par les Services B14+/B18+ et n'est pas assignable depuis le corps JSON.
- **ResolutionFactory** : plus de `create()` dans `definition()`. `proposal_id => Proposal::factory()` (résolu par Laravel au moment de l'écriture) ; `request_id` lu via closure sur la proposition résolue (via `DB::table` pour un typage strict PHPStan niveau 8).
- **Factories simplifiées** : plus de `padRight` pour contourner les CHECK retirés.
- **Tests enums simplifiés** : suppression de `test_default_state_is_draft` et `test_default_state_is_proposed`.
- **HelpRequestsSchemaTest adapté** : renommage de `test_check_constraints_reject_invalid_state_and_short_body` en `test_check_constraints_reject_invalid_state_values` ; retrait de l'assertion sur `comments.body` ; test NOT NULL simplifié.

Contrôles exécutés localement sous PHP 8.4.15 après fusion et corrections :

| Commande | Résultat observé |
|---|---|
| `composer lint` (Pint `--test`) | `{"tool":"pint","result":"passed"}` |
| `composer analyse` (PHPStan niveau 8) | `[OK] No errors` |
| `composer test` | **253 tests / 2454 assertions, OK** en 4,95 s (ancien 122 → 253 avec B07–B32 de main) |
| `composer test:integration` | **141 tests / 1266 assertions, OK** en 80,27 s sur PostgreSQL 17 sur `haas_capsules_test` (ancien 43 → 141 avec intégrations B07–B32) |
| `composer audit --locked --no-interaction` | `No security vulnerability advisories found.` |
| `composer validate --strict --no-check-publish` | `./composer.json is valid` |

Nouveaux commits sur la branche : `b152fc1` (merge main) et `12c0d50` (corrections). HEAD à `12c0d50`, sept commits ahead of `origin/main` au total (dont le merge commit).

## 2026-10-07 — Lot B22 · Schéma des capsules

Branche `backend/capsules-laboratoire-b22-schema` dérivée de `backend/capsules-laboratoire` (`71daddf`, qui a déjà fusionné `origin/main` `7a8c672` et porte les enums de PR #12 `CapsuleVersionState`, `LabRunState`, `ComparisonState/Outcome`). La table `help_requests` n'est pas dans `main` au 2026-10-07 : la colonne `capsules.source_request_id` est posée en UUID nullable **sans FK**, pour ne pas bloquer B22 sur la fusion de B11. La FK sera ajoutée dans une migration de raccord quand B11 sera dans `main`.

### Décisions clefs

- **Isolation stricte capsule / brique / laboratoire.** Aucune FK du schéma B22 ne pointe vers `lab_definitions`, `lab_runs`, `lab_results`, `test_events`, `test_orders` ni `demo_orders` ; une requête `information_schema` est inscrite au test `CapsulesMigrationTest::test_capsules_schema_has_no_foreign_key_toward_lab_or_test_tables`. Les six FK réelles sont `capsules.owner_id → users`, `capsule_versions.capsule_id → capsules`, `capsule_versions.reviewer_id → users (nullable)`, `capsule_contributors.version_id → capsule_versions`, `capsule_contributors.user_id → users (restrictOnDelete)`, `artifacts.version_id → capsule_versions`.
- **Contrainte XOR source / origine éditoriale.** `capsules_source_xor` impose `(source_request_id IS NULL) <> (editorial_origin IS NULL)` : une capsule a toujours exactement une origine, jamais les deux ni aucune.
- **Index partiel approved.** `artifacts_version_sha_approved_unique` protège un artefact approuvé unique par (version, digest) sans empêcher l'historique inactif.
- **CHECK published_at ↔ state.** `published_at IS NULL OR state = 'published'` ferme la porte à toute pose de date de publication en dehors du Service de publication (B25).
- **Convention de nommage.** Migrations en `create_<table>_table.php` sans préfixe de lot, cohérent avec B11 (`create_help_requests_table.php`), B35 (`create_test_events_table.php`) et B38 (`create_demo_orders_table.php`).

### Fichiers créés

- Enums : `backend/app/Enums/Capsules/{CapsuleVisibility,ContributionRole,ArtifactDistributionStatus}.php`.
- Modèles : `backend/app/Models/Capsules/{Capsule,CapsuleVersion,CapsuleContributor,Artifact}.php`.
- Migrations : `backend/database/migrations/2026_10_07_013053_create_capsules_table.php`, `2026_10_07_013654_create_capsule_versions_table.php`, `2026_10_07_014031_create_capsule_contributors_table.php`, `2026_10_07_014215_create_artifacts_table.php`.
- Factories : `backend/database/factories/Capsules/{Capsule,CapsuleVersion,CapsuleContributor,Artifact}Factory.php`.
- Tests Unit : `backend/tests/Unit/Enums/Capsules/{CapsuleVisibility,ContributionRole,ArtifactDistributionStatus}Test.php`.
- Tests Integration : `backend/tests/Integration/Capsules/{CapsulesSchema,CapsuleVersionsSchema,CapsuleContributorsSchema,ArtifactsSchema,CapsulesMigration}Test.php`.

### Contrôles exécutés et résultats réels

| Commande | Résultat observé | Environnement |
|---|---|---|
| `composer lint` | `{"tool":"pint","result":"passed"}` | PHP 8.4.15 Laragon, Windows |
| `composer analyse` | **[OK] No errors** (PHPStan/Larastan niveau 8) | idem |
| `composer test` | **284 tests / 2510 assertions, OK** en 21,934 s | idem |
| `composer test:integration` (filtre `Capsules\|Artifacts`) | **31 tests / 70 assertions, OK** en 33,784 s | PostgreSQL 17 `haas_capsules_test`, rôle `haas_test`, `TestDatabaseGuard` accepté sans contournement |
| `composer test:integration` (suite complète) | **164 tests / 1320 assertions, OK** en 2 min 51 s | idem |
| CI distante | NON EXÉCUTÉE à ce stade | branche non poussée |

### Commits locaux (branche `backend/capsules-laboratoire-b22-schema`)

1. `22d95b1` `feat(capsules): poser les enums de visibilité, rôle et distribution`
2. `9304cdd` `feat(capsules): créer la table capsules avec contrainte XOR`
3. `b581867` `feat(capsules): créer la table capsule_versions`
4. `5a9d488` `feat(capsules): créer la table capsule_contributors`
5. `f9fd15f` `feat(capsules): créer la table artifacts`
6. `1fa173b` `test(capsules): vérifier la régression des migrations et l'isolation`
7. commit documentaire courant.

### Questions ouvertes (non bloquantes, choix dégradés pris)

- **Q1 — FK `capsules.source_request_id` vers `help_requests`.** La table `help_requests` est sur `backend/communaute-entraide-b11-schema`, non fusionnée. Choix retenu : UUID nullable sans FK. Migration de raccord à prévoir après fusion de B11, ou dans une version de B23/B25 qui en a besoin pour une Policy.
- **Q2 — `CapsuleVisibility`.** `CAHIER_DES_CHARGES.md:869` cite « visibility » sans énumérer. Choix retenu : `visible|hidden`. À confirmer par le relecteur.
- **Q3 — `editorial_origin`.** Type non spécifié. Choix retenu : `string(100)` nullable sous CHECK format. À confirmer.
- **Q4 — `ContributionRole`.** Valeurs non spécifiées. Choix retenu : `author|reviewer|contributor|maintainer`. À confirmer. Le rôle `reviewer` est documentaire ici ; la revue indépendante (B24) sera posée sur `capsule_versions.reviewer_id`, pas sur ce pivot.
- **Q5 — `ArtifactDistributionStatus`.** Choix retenu : `inactive|approved`, défaut `inactive` conforme à `tasks.json:329`. À confirmer.
- **Q6 — Convention nommage migrations.** `main` mélange les deux conventions `bXX_create_*` (Ousseynou) et `create_<table>_table` (mdev44). Alignement sur ma convention antérieure. À confirmer par le relecteur.

### Limites et étapes suivantes

- B22 est backend seul : aucun Service de création/publication, aucune Policy, aucune route livrés — ces couches sont les lots B23 à B27.
- Les transitions `CapsuleVersionState` restent celles définies par PR #12 ; `in_review → published` et `published → withdrawn` sont inscrites comme déduites ; leur verrouillage côté Service appartient à B24/B25/B31.
- Aucun fichier interdit n'a été touché : `config/`, `bootstrap/`, `phpunit.xml`, `.env.example`, `composer.*`, `.github/workflows/`, `routes/api.php`, `docs/OPENAPI.yaml` sont intacts.
- B22 ne livre aucune route API. Aucune ligne à ajouter dans `docs/OPENAPI.yaml`.
- Aucun push, aucune PR ouverte, aucune revue humaine simulée.

## 2026-10-07 — Reprise B22 : intégration B11, FK source, rôles cahier, support de test

Statut proposé : B22 **préparé, PR en brouillon**, aucun `DONE`.

- `git merge --no-ff origin/backend/communaute-entraide` fusionné localement (`478d1e0`) : B11 complet d'Ousseynou/mdev44 (6 migrations help_requests → request_technologies → proposals → comments → resolutions → b11_add_collaboration_versions, modèles, factories, enums `HelpRequestState`/`ProposalState`, SHA256SUMS mis à jour par Ousseynou, docs AI_USAGE et participants). Conflits textuels résolus manuellement sur les trois fichiers autorisés uniquement : `docs/AI_USAGE.md` (3 sections B11 conservées, 0 ligne HEAD apportée dans la zone), `PROGRESS.md` et `HANDOFF.md` de ce répertoire (sections B11 du 2026-10-03 remises AVANT la section B22 du 2026-10-07, aucune reformulation). `composer.lock`/`composer.json` inchangés par le merge.
- `feat(capsules): poser la clé étrangère vers help_requests pour la source` (`f59b779`) : `capsules.source_request_id` devient `foreignUuid('help_requests')->restrictOnDelete`. La contrainte XOR `capsules_source_xor` reste inchangée. 4 tests FK ajoutés dans `CapsulesSchemaTest` (UUID réel accepté, UUID inexistant refusé, suppression de la demande source refusée, XOR dans les deux sens). `CapsulesMigrationTest::test_capsules_schema_has_no_foreign_key_toward_lab_or_test_tables` ajoute `capsules.source_request_id` à la liste attendue des 7 FK du schéma B22 (6 originelles + source). → **Q1 fermée** par la pose de la FK.
- `fix(capsules): aligner contribution_role sur le vocabulaire du cahier` (`bc7b8ee`) : enum `ContributionRole` reconstruit avec `diagnosis|fix|documentation|test|case`, union littérale de `CAHIER_DES_CHARGES.md:364` (« cas, diagnostic, correctif, documentation ») et `CAHIER_DES_CHARGES.md:508` (« diagnostic, correction, documentation, test »). Facteurs et tests mis à jour. L'enum des states de la migration `create_capsule_contributors_table` itère `ContributionRole::cases()` : la nouvelle liste se propage sans édition de migration. → **Q4 fermée.**
- `test(capsules): déposer les tables de capsules avant le rollback des migrations partagées` (`5a49dab`) : la FK `capsules.source_request_id` empêchait le rollback de `help_requests` (SQLSTATE 2BP01). Nouveau support `Tests\Support\Mdev44\DomainTables::dropAll()` qui drope `artifacts → capsule_contributors → capsule_versions → capsules` (enfants d'abord). Appelé en une ligne dans `HelpRequestsMigrationTest::test_b11_can_be_rolled_back_…` et dans `IdentityMigrationTest::runIdentityMigration('down')`. Modification cross-domain minimale (2 lignes par fichier : import + appel), sans toucher à aucune migration applicative. Un test `DomainTablesTest` vérifie que `dropAll()` fonctionne sur une base migrée à neuf et préserve `help_requests`/`users`.

Vérifier §24 du cahier : les 4 tables (capsules, capsule_versions, capsule_contributors, artifacts) couvrent les colonnes citées dans `CAHIER_DES_CHARGES.md:869-872` sans écart. `state` utilise bien `CapsuleVersionState` du lot 1 (brouillon, en revue, à corriger, publiée, retirée).

Technologies de capsule : `CAHIER_DES_CHARGES.md:456` cite « technologies, versions compatibles déclarées » mais aucune table `capsule_technologies` n'est nommée dans le dictionnaire §24. **Q7 reste ouverte**, aucune table de liaison inventée dans ce lot. Question posée au relecteur et consignée dans le corps de la PR.

Contrôles exécutés après reprise :

| Commande | Résultat observé | Environnement |
|---|---|---|
| `composer lint` | `{"tool":"pint","result":"passed"}` | PHP 8.4.15 Laragon |
| `composer analyse` | `[OK] No errors` (PHPStan niveau 8) | idem |
| `composer test` | **315 tests / 2541 assertions, OK** en 5,975 s | idem |
| `composer test:integration` (filtre `Capsules|Artifacts`) | **33 tests / 74 assertions, OK** après la FK | PostgreSQL 17 `haas_capsules_test` |
| `composer test:integration` (suite complète) | **185 tests / 1400 assertions, OK** en 1 min 50 s | idem |

Nouveaux commits locaux (10 au total sur la branche) :

8. `478d1e0` `chore(capsules): intégrer le schéma B11 pour la clé étrangère de source`
9. `f59b779` `feat(capsules): poser la clé étrangère vers help_requests pour la source`
10. `bc7b8ee` `fix(capsules): aligner contribution_role sur le vocabulaire du cahier`
11. `5a49dab` `test(capsules): déposer les tables de capsules avant le rollback des migrations partagées`
12. commit documentaire courant.

Questions ouvertes restantes :
- Q2 — `CapsuleVisibility` : valeurs `visible|hidden`, non littéralement citées. À confirmer.
- Q3 — `editorial_origin` : type `string(100)` nullable sous CHECK, non littéralement cité. À confirmer.
- Q5 — `ArtifactDistributionStatus` : valeurs `inactive|approved`, défaut `inactive` conforme à `tasks.json:329`. À confirmer.
- Q6 — Convention nommage migrations : `create_<table>_table.php` sans préfixe de lot, cohérent B11/B35/B38 côté mdev44 mais écart avec `bXX_create_*` d'Ousseynou. À arbitrer par le responsable 1.
- Q7 — Table `capsule_technologies` : le cahier parle de technologies au contenu d'une capsule (`CAHIER:456`) mais sans table nommée dans le §24. Pas livrée dans B22, à trancher par le relecteur.

B22 reste **préparé** et pointe vers une PR en brouillon ; `DONE` n'est pas prononcé avant revue humaine et fusion.

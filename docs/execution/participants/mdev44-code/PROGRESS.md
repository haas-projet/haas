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

## 2026-10-07 — Lot B23 · Brouillons de capsule

Statut proposé : B23 **préparé, PR en brouillon**, aucun `DONE`. Branche `backend/capsules-laboratoire-b23-brouillons`, dérivée de `backend/capsules-laboratoire-b22-schema` (`58fe552`) et empilée sur la PR #29.

### Décisions clefs

- **Pas de PATCH en B23.** Le cahier §26 ne liste que `POST /capsules` et `POST /capsules/{id}/versions` ; aucune route d'édition `in-place` n'est documentée. Lecture restrictive : B23 n'expose pas de PATCH. Question ouverte Q8 : décision avant B24/B25.
- **Source `help_request`** réservée à l'auteur de la demande, résolution active obligatoire (`revoked_at IS NULL`). L'alternative « auteur de la proposition acceptée » n'est pas documentée. Lecture restrictive : auteur seul. Question ouverte Q9.
- **Source `editorial`** réservée aux `moderator`/`admin` (§07 CAHIER:220, §13 CAHIER:456 : « origine éditoriale clairement signalée pour les briques de démonstration »).
- **Owner uniquement pour la version-brouillon** suivante (B23). Les contributeurs reviewer/maintainer sont habilités plus tard par B24/B25.
- **IdempotencyService réutilisé.** Pas de header `X-Idempotent-Replay` : le status 201 est renvoyé aux deux appels (le second ne rejoue pas la closure `operation`). Documenté dans le fragment OpenAPI.
- **Audit interne du domaine** : nouvelle classe `WriteCapsuleAudit` qui insère dans `content_revisions` sans toucher `AuditWriter.php` (fichier du socle, profilé pour Profile uniquement). Même contrat transactionnel : rollback annule l'écriture et l'audit.
- **ApiExceptionRenderer non touché** (fichier du socle, interdit). `ValidationException` natif Laravel pour les 422 (source introuvable, pas de résolution active) ; `AuthorizationException` pour les 403.
- **Pivot capsule_version_technologies** aligné sur `request_technologies` : clé primaire composite, pas d'UUID. Supporte « versions compatibles déclarées » (CAHIER:456).

### Fichiers créés / modifiés

- Enums : `App\Enums\Capsules\CapsuleSourceKind` (help_request/editorial).
- Data : `App\Data\Capsules\{CapsuleDraftData, VersionDraftData, TechnologyAttachmentData}`.
- Policy : `App\Policies\CapsulePolicy` (proposeEditorial, proposeFromHelpRequest, createVersionDraft, editDraft).
- Services : `App\Services\Capsules\{CreateCapsuleDraftService, CreateCapsuleVersionDraftService, WriteCapsuleAudit}`.
- HTTP : `StoreCapsuleDraftRequest`, `StoreCapsuleVersionDraftRequest`, `StoreCapsuleDraftController`, `StoreCapsuleVersionDraftController`, `CapsuleDraftResource`, `CapsuleVersionDraftResource`.
- Migration : `2026_10_07_123901_create_capsule_version_technologies_table.php` + relation `CapsuleVersion::technologies()`.
- Routes : `backend/routes/api/capsules-lab.php` (deux routes, `capsules.drafts.store` et `capsules.versions.drafts.store`).
- Fragment OpenAPI : `docs/api/openapi/capsules-lab.yaml` (operationIds, schémas).
- Support de test : `DomainTables::TABLES` étendu à `capsule_version_technologies` avant `capsule_versions`.
- Tests : `CapsuleSourceKindTest`, `CapsuleDraftDataTest`, `CapsuleVersionTechnologiesSchemaTest`, `CreateCapsuleDraftServiceTest`, mise à jour `CapsulesMigrationTest`.

### Fichiers hors de mon domaine modifiés

- `docs/OPENAPI.yaml` : +4 lignes (deux entrées `$ref` pour les routes B23). Commit dédié `docs(api): référencer les routes de brouillon de capsule dans OPENAPI.yaml`.
- `docs/api/generated/haas-api.d.ts` : régénéré par `scripts/generate-api-types.php` (quatre nouveaux types TypeScript correspondant aux schémas B23). Commit dédié `docs(api): régénérer les types générés depuis le fragment capsules-lab`.

Aucun autre fichier du socle n'a été touché : `routes/api.php`, `bootstrap/`, `config/`, `composer.*`, `phpunit.xml`, `.github/`, `SHA256SUMS` sont intacts.

### Contrôles exécutés et résultats réels

| Commande | Résultat observé | Environnement |
|---|---|---|
| `composer lint` | `{"tool":"pint","result":"passed"}` | PHP 8.4.15 Laragon |
| `composer analyse` | `[OK] No errors` (PHPStan niveau 8) | idem |
| `composer test` | **323 tests / 2648 assertions, OK** en 5,697 s | idem |
| `composer test:integration` (filtre `Capsules|Artifacts`) | 14 tests / 28 assertions, OK | PostgreSQL 17 `haas_capsules_test`, `TestDatabaseGuard` respecté |
| `composer test:integration` (suite complète) | **199 tests / 1430 assertions, OK** en 1 min 57 s | idem |

### Commits locaux (branche `backend/capsules-laboratoire-b23-brouillons`)

1. `46db318` `feat(capsules): poser la table de liaison capsule_version_technologies`
2. `7e8f180` `feat(capsules): poser le data pour les brouillons de capsule`
3. `f9f7627` `feat(capsules): poser la Policy et les services de brouillon`
4. `a849f9b` `feat(capsules): exposer les routes de brouillon et leur contrat OpenAPI`
5. `5d84bf9` `docs(api): référencer les routes de brouillon de capsule dans OPENAPI.yaml`
6. `60fceb0` `docs(api): régénérer les types générés depuis le fragment capsules-lab`
7. `cd48b6a` `test(capsules): étendre le rollback de régression au pivot technologies`
8. commit documentaire courant.

### Questions ouvertes

- Q8 — PATCH /capsules/{id}/versions/{version} pour éditer un brouillon : non documenté dans §26. Non livré en B23. Décision avant B24/B25.
- Q9 — Source `help_request` : qui peut proposer, l'auteur de la demande seul (lecture restrictive) ou aussi l'auteur de la proposition acceptée ?
- Q10 — Table `capsule_version_technologies` : non explicitement nommée dans §24 CAHIER:869-872. Décision de propriétaire du domaine attendue.
- Q11 — Flag replay côté client : IdempotencyService ne distingue pas 201/200 au rejeu. Faut-il exposer un header `X-Idempotent-Replay` à partir d'une relecture de `api_idempotency.created_at`, ou laisser le client déduire de sa clé ?
- Q2–Q7 de B22 restent ouvertes.

### Limites et étapes suivantes

- B23 est backend seul : aucun composant React livré.
- Les transitions `CapsuleVersionState` autres que `draft` sont servies par B24/B25.
- Aucun push, aucune PR ouverte à ce stade dans PROGRESS ; publication autorisée pour ce lot, consignée dans HANDOFF.

## 2026-10-07 — Suite B23 : PATCH, tests HTTP, décisions Q8/Q9

Statut : B23 reste **préparé, PR en brouillon** sur la PR #30. Aucun `DONE`.

### Décisions prises

- **Q8 fermée** : PATCH `/api/v1/capsules/{capsule}/versions/{version}` livré en B23. Edition autorisée pour les états `draft` et `changes_requested` seulement (CAHIER_DES_CHARGES.md:293 pour la boucle de revue B24). `version_label`, `state`, `owner_id`, `reviewer_id`, `published_at`, `id`, `capsule_id` sont refusés en 422 par le FormRequest. Pas d'`Idempotency-Key` sur ce PATCH : patron B16 (`UpdateHelpRequestRequest` sur `backend/communaute-entraide-b16`) utilise `lock_version` comme unique mécanisme anti-doublon.
- **Q9 fermée** : la Policy `proposeFromHelpRequest` autorise l'auteur de la demande **et** l'auteur de la proposition acceptée par la résolution active (`revoked_at IS NULL`). CAHIER_DES_CHARGES.md:265 « L'auteur d'une résolution ou un contributeur autorisé propose une capsule ». Le créateur devient `owner_id` (serveur). Trois tests HTTP distincts couvrent les trois cas.
- **Q11 (clarification)** : au deuxième POST avec la même `Idempotency-Key` et la même charge, `IdempotencyService` relit `api_idempotency` et renvoie le même `StoredCommandResult` que la première écriture ; le `status` stocké est `201`, identique au premier appel. B23 ne distingue donc pas « création » et « rejeu » dans la réponse HTTP (pas de header `X-Idempotent-Replay`). Décision ouverte : exposer le header en relisant `api_idempotency.created_at` ou laisser le client déduire via sa clé.

### Nouveaux fichiers

- Migration `2026_10_07_172331_add_lock_version_to_capsule_versions_table.php` (additive, défaut `1`, down() tolérant au drop préalable pour cohabiter avec `DomainTablesTest`).
- Data : `App\Data\Capsules\UpdateDraftData` (lock_version obligatoire, body/limits/technologies optionnels, aucun champ serveur).
- Exception de domaine : `App\Exceptions\Capsules\StaleCapsuleVersion` (lock_version périmé) ; le Controller la transforme en 409 pour respecter `DomainBoundariesTest`.
- Service : `App\Services\Capsules\UpdateCapsuleVersionDraftService` (transaction, lockForUpdate, vérifie appartenance capsule/version, Policy sur l'état verrouillé, incrémente lock_version, synchronise les technologies, audit dans la même transaction).
- Policy `editDraft(?User, Capsule, CapsuleVersionState, string $versionId)` : autorise owner OU contributeur de la version précise, pour les états `draft`/`changes_requested`.
- FormRequest `UpdateCapsuleVersionDraftRequest` : refuse les champs serveur et exige au moins un champ modifiable.
- Controller `UpdateCapsuleVersionDraftController` (mince) : convertit `StaleCapsuleVersion` → 409.
- Resource `CapsuleVersionDraftResource` : expose `lock_version` au client.
- Route `PATCH /api/v1/capsules/{capsule}/versions/{version}` (name `capsules.versions.drafts.update`).
- Fragment OpenAPI : opération `updateCapsuleVersionDraft` + schéma `CapsuleVersionDraftUpdate`.
- Tests : `CapsuleDraftHttpTest` (20 cas HTTP sur POST), `CapsuleDraftUpdateHttpTest` (11 cas HTTP sur PATCH).

### Contrôles finaux après la suite B23

| Commande | Résultat observé |
|---|---|
| `composer lint` | `{"tool":"pint","result":"passed"}` |
| `composer analyse` | `[OK] No errors` (PHPStan niveau 8) |
| `composer test` | **323 tests / 2675 assertions, OK** en 11,832 s |
| `composer test:integration` (suite complète) | **230 tests / 1495 assertions, OK** en 4 min 51 s |

### Nouveaux commits locaux (suite B23)

- `64b0cc0` `test(capsules): couvrir les routes de brouillon par HTTP et étendre la Policy`
- `db8cab3` `feat(capsules): poser le verrou optimiste lock_version sur capsule_versions`
- `f60fa14` `feat(capsules): poser le service d'édition de brouillon avec verrou optimiste`
- `b96f808` `feat(capsules): exposer la route PATCH d'édition de brouillon`
- `6fa306e` `docs(api): référencer la route PATCH d'édition de brouillon dans OPENAPI.yaml`
- `5935fe6` `docs(api): régénérer les types générés pour inclure le PATCH de brouillon`
- `d9cb216` `fix(capsules): isoler le service du domaine HTTP via StaleCapsuleVersion`
- `c49044f` `fix(capsules): rendre le down() de lock_version tolérant au drop préalable`
- commit documentaire courant.

### Questions ouvertes restantes

- Q10 — Nom de la table pivot `capsule_version_technologies` : non explicitement cité dans §24. Décision de propriétaire du domaine, en attente de confirmation du relecteur.
- Q11 — Header `X-Idempotent-Replay` à exposer ou non.
- Q2–Q7 de B22 restent ouvertes.

## 2026-10-07 — Lot B24 · Soumettre à la revue

Statut proposé : **préparé, PR en brouillon**, aucun `DONE`. Branche `backend/capsules-laboratoire-b24-revue`, dérivée de `backend/capsules-laboratoire-b23-brouillons` (`93fafea`).

### Décisions clefs

- **Deux transitions** : `draft → in_review` et `changes_requested → in_review` (CAHIER_DES_CHARGES.md:293). La transition `in_review → published` reste pour B25. Toute autre transition est refusée.
- **Soumission** : owner ou contributeur de la version. Contenu minimal exigé (body ≥ 20 non blancs ET limits non vide) sinon 422 ciblé avec champs manquants.
- **Revue « demander des corrections »** : moderator/admin qui N'EST NI owner NI contributeur de la version. Un admin auteur ou admin contributeur ne se relit pas, même avec les droits techniques (CAHIER_DES_CHARGES.md:462). Transition `in_review → changes_requested` ; `reviewer_id` posé sur `capsule_versions` ; note 20-2000 caractères non blancs enregistrée dans `capsule_version_reviews`.
- **Table `capsule_version_reviews`** : décision du propriétaire du domaine, non nommée dans §24. Colonnes id/version_id/reviewer_id/decision/note/created_at. Immuable par Service (pas d'`updated_at`).
- **Notification « Revue de capsule terminée »** : non livrée en B24. L'API `NotificationEvent` du socle n'accepte que `kind === 'profile.moderated'` (`backend/app/Data/Notifications/NotificationEvent.php`). Toute extension exige la modification d'un fichier du socle (interdit). Question ouverte Q12 : élargir l'enum `kind` ou poser un adaptateur local.

### Fichiers créés

- Enum : `App\Enums\Capsules\ReviewDecision` (request_changes).
- Migration : `2026_10_07_174813_create_capsule_version_reviews_table.php` + CHECK note 20-2000.
- Modèle `App\Models\Capsules\CapsuleVersionReview` + factory.
- Exception de domaine : `App\Exceptions\Capsules\InsufficientDraftContent` (contenu minimal manquant) ; le Controller la transforme en 422.
- Policy étendue : `submitForReview`, `reviewVersion`.
- Services : `SubmitCapsuleVersionForReviewService`, `RequestChangesOnCapsuleVersionService` (transactions, audit atomique).
- FormRequest `RequestChangesRequest` ; Controllers `SubmitCapsuleVersionForReviewController`, `RequestChangesController`.
- Routes : `POST .../submit-review` et `POST /admin/capsules/.../request-changes`.
- Fragment OpenAPI + 2 nouveaux schémas (`CapsuleVersionReviewInput`, `CapsuleVersionReview`).
- Tests : `CapsuleVersionReviewsSchemaTest` (4 cas) + `CapsuleReviewHttpTest` (14 cas HTTP).
- Support de test : `DomainTables` inclut `capsule_version_reviews` ; `CapsulesMigrationTest` incrémente son compteur à sept.

### Fichiers hors de mon domaine modifiés

- `docs/OPENAPI.yaml` : **+4 lignes** (deux routes B24). Commit `6831521`.
- `docs/api/generated/haas-api.d.ts` : **régénéré** par `scripts/generate-api-types.php`. Commit `51370b8`.

Aucun autre fichier du socle n'a été touché.

### Contrôles finaux

| Commande | Résultat observé |
|---|---|
| `composer lint` | `passed` |
| `composer analyse` | `[OK] No errors` |
| `composer test` | **323 tests / 2723 assertions, OK** en 10,975 s |
| `vendor/bin/phpunit --testsuite Integration` (direct, composer time-out) | **248 tests / 1523 assertions, OK** en 5 min 25 s |

### Commits locaux

- `21fdf30` policy + services
- `6831521` $ref OPENAPI
- `51370b8` types régénérés
- commits de schéma, routes et tests déjà poussés via le lot.

### Questions ouvertes

- Q12 — Notifier « Revue de capsule terminée » : l'API `NotificationEvent` restreint `kind` à `profile.moderated`. Options : élargir l'enum côté socle (hors de mon domaine) ou créer un canal local. Décision à prendre avant B25.
- Q10 (B23) — Nom du pivot `capsule_version_technologies` reste ouvert.
- Q11 (B23) — Header `X-Idempotent-Replay` reste ouvert.
- Q2–Q7 de B22 restent ouvertes.

## 2026-10-07 — Préparation technique Codex de la PR #33

Source `e5dabac` préservée. Soumission et corrections exigent intention idempotente et verrou, relisent les droits actuels et invalident un rejeu devenu périmé. Le journal capture `reviewed_lock_version`, conserve notes/reviewers et refuse leur modification ou suppression. Aucune acceptation/publication B25 ajoutée.

Contrôles réels : HTTP/readiness **21 / 81**, puis HTTP/readiness/schema/six courses **33 / 162**, compléments SQL rollback/upgrade et DTO **23 / 90**, PHPStan sans erreur après les compléments, Pint passé, documents **18 + 7**, types **36**. Détails et limites dans `docs/quality/B24_REVIEW_READINESS.md`.

Statut : préparation locale en cours, non `DONE`. Parent B23/B22/main et suites combinées à intégrer. Q12 doit être raccordée à l'outbox commune pour les corrections demandées avant présentation à la revue humaine ; publication/acceptation demeurent B25. CI et revue humaine finales en attente.

## 2026-10-07 — Préparation technique Codex de la PR #30

Le travail distant `93fafea`, notamment Q8/Q9, est conservé. Les défauts de provenance, conflits, effacement des limites, droits actuels et rejeu sont corrigés ; la projection des réponses est attachée à la version autorisée. Les champs inconnus, technologies inconnues ou répétées, caractères de contrôle et secrets indicatifs sont refusés. Les détails, incidents et commandes réels sont dans `docs/quality/B23_DRAFT_READINESS.md`.

- Readiness et six courses entre deux processus PostgreSQL : **13 tests / 111 assertions, OK** sur `haas_b23_review_test`.
- Suite capsules complète avant synchronisation B22/main : **92 tests / 290 assertions, OK**.
- Unit/Feature/Architecture : **323 tests / 2675 assertions, OK**.
- PHPStan : `[OK] No errors`, après description du cast enum réel et de la Policy SQL impure ; aucune suppression d'erreur. Pint : `passed`.
- Documents : 18 + 7 contrôles documentaires OK ; **33 types API à jour**.

Statut : correctif local préparé pour synchronisation B22/main, suite combinée complète et CI distante. Revue humaine en attente ; aucune approbation attribuée, aucun `DONE`, aucun frontend. L'intégrateur rapporte le SHA réel après commit.

### Après synchronisation B22/main — parent `94aeae9`

Refus de source masquée dans la Policy relue sous verrou, y compris au rejeu ; trois nouvelles régressions HTTP/service et une septième course réelle. Fixture publiée PATCH conforme aux contraintes B22 (date et reviewer fictif vérifié distinct). Ciblés **28 / 158**, Pint et PHPStan verts. Suite SQL complète initiale interrompue à la demande de l'intégrateur après un échec de fixture : aucune réussite globale attribuée ; relance complète requise après ce commit. Voir le complément de `B23_DRAFT_READINESS.md`.

## 2026-10-05 — Lot 2 B35 brique B1 (branche dérivée)

Lot B35 livré sur une branche dérivée `backend/capsules-laboratoire-b35-brique-b1` créée depuis `origin/backend/capsules-laboratoire`. La branche parent porte le lot 1 enums ; la PR #12 y est ouverte sur `main` et sa fusion est attendue avant le reciblage de cette PR dérivée.

Portée livrée : la brique B1 complète du laboratoire (B35), c'est-à-dire tables `test_events`/`test_orders`, modèles `TestEvent`/`TestOrder`, DTO `TestEventData`, résultat typé `ProcessedTestEvent{order, duplicate}`, service transactionnel `ProcessTestEventService` et scénarios B1-01 à B1-05 réels sur PostgreSQL. Aucun paiement réel n'est manipulé.

Décisions concrètes :

- Migrations à la convention Laravel pure, nom descriptif sans préfixe de lot : `create_test_events_table.php` et `create_test_orders_table.php`. Dérogation assumée à `docs/execution/BACKEND_A_TROIS.md:110` (« une migration par table, nom descriptif, sans préfixe de lot »), à arbitrer par le relecteur. Nom des tables aligné sur la « Séparation des données de test » décrite au cahier des charges §24.
- Namespaces `App\Models\Lab`, `App\Data\Lab`, `App\Services\Lab`, `Database\Factories\Lab` et sous-répertoire `tests/Integration/Lab/`.
- `run_id` est un identifiant logique du run de laboratoire. Il n'y a pas de FK vers `lab_runs` : `lab_runs` (B33) vit dans la base `haas_app`, tandis que `test_events`/`test_orders` appartiennent conceptuellement à `haas_lab` (cahier des charges §15 et §33). Aucune FK ne peut être posée en travers de deux bases distinctes ; la documentation en en-tête des migrations l'énonce explicitement.
- Idempotence portée côté base par la contrainte unique `(run_id, event_id)` sur `test_events` et par la contrainte unique sur `test_orders.source_event_id`. `ProcessTestEventService::handle` enveloppe l'insertion dans `DB::connection(LabConnection::NAME)->transaction(...)` via `TestEvent::query()->insertOrIgnore(...)` : aucune course SELECT-puis-INSERT, le rejeu renvoie exactement la même commande sans écriture additionnelle. Le résultat typé `ProcessedTestEvent{order, duplicate}` distingue B1-01 (duplicate=false) de B1-02 (duplicate=true).
- Le point de bascule applicatif vers `haas_lab` est `App\Models\Lab\LabConnection::NAME`. Les modèles `TestEvent`/`TestOrder` lisent la constante via `getConnectionName()`, les migrations déclarent `$connection = LabConnection::NAME`, et le service passe par `DB::connection(LabConnection::NAME)` : aucune chaîne littérale de connexion n'est écrite ailleurs dans le domaine. Le service n'utilise plus `DB::table(...)`.
- Aucune route HTTP exposée en B35 : la brique est appelée par le worker (B36) non encore livré. `routes/api/capsules-lab.php` reste inchangé.
- Aucune donnée réelle manipulée : fixtures `evt_*`/`ord_*`/montants aléatoires bornés `[100, 100 000]` en centimes, trois devises fictives `EUR`/`USD`/`GBP`.

Écart ouvert : les tables `test_events`/`test_orders` sont pour l'instant créées et testées sur la **connexion par défaut** (`haas_capsules_test` dans ma session) parce que `LabConnection::NAME = null`. Le câblage de `haas_lab` demandera à toucher plusieurs endroits de concert :

- côté domaine capsules/laboratoire : changer la valeur de `App\Models\Lab\LabConnection::NAME` ;
- côté socle (responsable 1, fichiers réservés par `docs/execution/BACKEND_A_TROIS.md:103`) : ajouter la connexion `lab` dans `backend/config/database.php`, étendre `backend/phpunit.xml` et la CI ;
- côté tests : aligner la connexion secondaire utilisée pour le nettoyage hors transaction PHPUnit (`test_events_cleanup` dans `ConcurrentTestEventTest`) et la configuration `RefreshDatabase` dans `PostgresTestCase`.

Au moment du câblage, les environnements déjà migrés devront **rejouer les migrations** ou **déplacer manuellement les tables** `test_events` et `test_orders` vers la base `haas_lab` : comme les migrations lisent `LabConnection::NAME`, un simple changement de constante n'importe pas les tables existantes de la connexion par défaut vers la nouvelle connexion.

Demande à soumettre à `ousseynoufayeisidk-sys` pour la part socle.

Fichiers créés dans ce lot (dans mon domaine uniquement, aucun fichier d'un autre pilote ni fichier racine modifié) :

- `backend/database/migrations/2026_10_05_120000_create_test_events_table.php`
- `backend/database/migrations/2026_10_05_120100_create_test_orders_table.php`
- `backend/app/Models/Lab/{LabConnection,TestEvent,TestOrder}.php`
- `backend/database/factories/Lab/{TestEventFactory,TestOrderFactory}.php`
- `backend/app/Data/Lab/{TestEventData,ProcessedTestEvent}.php`
- `backend/app/Services/Lab/ProcessTestEventService.php`
- `backend/tests/Integration/Lab/{TestEventSchemaTest,ProcessTestEventTest,ConcurrentTestEventTest}.php`
- `backend/tests/Fixtures/process-test-event-concurrently.php`

Contrôles exécutés localement sous PHP 8.4.15 Laragon, sur `haas_capsules_test` :

| Commande | Résultat observé |
|---|---|
| `composer lint` (Pint `--test`) | `{"tool":"pint","result":"passed"}` |
| `composer analyse` (PHPStan niveau 8) | `[OK] No errors` |
| `composer test` (Unit + Feature + Architecture) | **148 tests / 977 assertions, OK** |
| `composer test:integration` | **59 tests / 417 assertions, OK** |

Décomposition par classe B35 (mesurée séparément) :

| Classe | Résultat |
|---|---|
| `tests/Integration/Lab/TestEventSchemaTest.php` | 6 tests / 26 assertions |
| `tests/Integration/Lab/ProcessTestEventTest.php` | 17 tests / 85 assertions |
| `tests/Integration/Lab/ConcurrentTestEventTest.php` | 1 test / 10 assertions |

Scénarios B1-01 à B1-05 réellement couverts :

- B1-01 nominal : un événement neuf crée exactement un `test_events` + un `test_orders` dans la même transaction, `processed_at` renseigné, résultat `duplicate=false`.
- B1-02 doublon : deux appels successifs avec les mêmes `(run_id, event_id)` renvoient la même commande sans doubler les lignes. Résultat `duplicate=false` au premier appel, `duplicate=true` au rejeu. Un rejeu avec un payload divergent (même clé mais autre montant/référence/devise) renvoie la commande d'origine inchangée — décision épinglée par un test, à arbitrer par le relecteur.
- B1-03 distincts : deux événements différents (même `run_id`) et le pendant inter-runs (`event_id` partagé, `run_id` distincts) produisent deux commandes.
- B1-04 invalide : 11 cas de données malformées (montant ≤ 0, devise `eur`/`EURO`, `event_id` vide/blanc/non trimmé/avec saut de ligne final, `order_ref` vide/avec saut de ligne final, `run_id` non UUID) sont tous rejetés par `ValidationException` **sans aucune écriture partielle** (`test_events` et `test_orders` restent vides). Les regex `event_id`/`order_ref`/`currency` portent le modificateur `D` pour empêcher un saut de ligne final de passer la validation.
- B1-05 concurrence : deux processus PHP indépendants lancés via `Symfony\Component\Process\Process`, synchronisés sur barrière `READY`/`GO`, soumettent le même `(run_id, event_id)`. Les deux processus sont pompés simultanément par une boucle `isRunning()` + `checkTimeout()` + `getIncrementalOutput()` : un `wait()` séquentiel ne pompe que son process et ferait disparaître la concurrence. Un trigger `pg_sleep(0.3)` restreint au `run_id` du test est posé via la connexion secondaire pour garantir un chevauchement reproductible (il est supprimé dans le `finally`). La contrainte unique PostgreSQL arbitre : exactement un `duplicate=false` (gagnant) et un `duplicate=true` (perdant), même `order_id` et même `source_event_id`, un seul événement et une seule commande en base. L'assertion `[false, true]` serait aussi vraie d'une exécution séquentielle ; la preuve de chevauchement est la sensibilité du test à une mutation du service en SELECT puis INSERT naïf, mesurée hors suite (17/20 échecs — 3/20 passent — sans trigger ; 20/20 échecs avec trigger — le trigger est donc nécessaire pour que la mesure soit reproductible).
- Atomicité : un test dédié force un `TestOrder::creating` qui lève et vérifie que la transaction du service annule l'insertion de l'événement — aucune ligne orpheline ne subsiste dans `test_events`.

Limites :

- Aucune route HTTP B35 exposée ; le wiring par un worker viendra avec B36. La brique est pour l'instant appelable uniquement en interne par un service Laravel ou un script fixture.
- Les tests B1-01 à B1-04 exécutent le service directement, sans passer par un contrôleur HTTP (B35 n'en a pas). Le test B1-05 lance de vrais processus PHP via `Symfony\Component\Process` : il s'appuie sur `PHP_BINARY` disponible dans la session, confirmé Laragon 8.4.15.
- Connexion `haas_lab` non encore câblée : voir « écart ouvert » plus haut.
- Les scénarios B22–B28 (capsules), B33–B34/B36–B38 (reste du lab), BV201–BV210 (vérifications) restent TODO.

Prochaines actions : obtenir une relecture humaine du lot B35 par `ousseynoufayeisidk-sys` ; soumettre à `ousseynoufayeisidk-sys` la demande de câblage de la connexion `haas_lab` dans `config/`, `phpunit.xml` et la CI. Les lots suivants (B22 schéma capsules, B33 registre lab) attendent respectivement B11 (collaboration) et B22.


## 2026-10-07 — B22 : corrections avant revue humaine

À la demande de l’utilisateur, Codex prépare la PR #29 sans effacer les preuves antérieures. Main `e3bd34c` intégré par merge normal `39d923c` ; conflits documentaires résolus en conservant les historiques. Date de publication/retrait corrigée dans une migration additive ; versions publiées, provenance, attributions et technologies protégées en SQL ; relecteur indépendant et FK RESTRICT ; lock positif et technologies déclarées ; champs serveur non mass assignables. Les migrations B23 de pivot et de verrou sont reprises sous leurs noms existants, sans doublon. Aucune dépendance ni route B22.

Preuves, commandes exactes, incidents de fixtures et limites : [B22_CAPSULE_SCHEMA.md](../../../quality/B22_CAPSULE_SCHEMA.md). Contrat : [CAPSULE_DATA.md](../../../architecture/CAPSULE_DATA.md). Hors SQL 319 / 2570 et SQL 232 / 1587 réussis, soit 551 tests / 4157 assertions uniques ; Pint, PHPStan 8 et Composer réussis. Base dédiée `haas_b22_review_test` sur PostgreSQL 17 local 55447 ; correctif de cookies simulés B17 repris sans assouplissement de production.

Statut : prêt localement pour la revue humaine, CI du SHA publié et revue humaine en attente ; aucun DONE ou BACKEND_GATE. Après contrôles et publication par l’intégrateur, examiner B22 puis intégrer son schéma dans B23/B24 et refaire les tests des consommateurs. Aucun push effectué par cet agent.

### Revalidation B22 avec main B14–B17 intégré

Après le correctif local `2148322`, l’intégrateur prépare un merge normal de main `b76612d1b6587119127fd364244f5248f05f1ff2`. Tests du code combiné réellement exécutés sur la base dédiée `haas_b22_review_test` (PostgreSQL 17, 55447) : hors SQL **335 / 3595**, SQL **399 / 3390**, soit **734 tests / 6985 assertions uniques réussis** ; Pint et PHPStan niveau 8 réussis. Les anciens 551 / 4157 restent une preuve historique et ne s’ajoutent pas à ce total. Journaux/JUnit ignorés dans `backend/storage/logs/b22-main-integration.*`. Détails : [B22_CAPSULE_SCHEMA.md](../../../quality/B22_CAPSULE_SCHEMA.md).

Le conflit du test de migration d’identité conserve les retraits des tables de capsules et des révisions communautaires. Aucun changement d’autorisation ou assouplissement de test. L’intégrateur consigne le SHA réel après création du merge puis publie la branche ; CI du SHA publié et revue humaine toujours à recevoir. Aucun DONE B22, frontend ou déploiement annoncé.

### Dernière revue B23 : validation et auteur source

Probes avant correction sur `1ddf681` : 13 / 55, dix échecs réels (500 de DTO, format nested perdu et source devenue inaccessible encore admise). Corps utile contrôlé sans transformation, PATCH blanc refusé, verrou entier JSON strict et auteur source actif/vérifié relu sous partage NOWAIT avec conflit explicite. Dix courses PostgreSQL, dont trois nouvelles pour les droits d’auteur tiers et les verrous croisés. Code définitif ciblé : **24 tests / 186 assertions réussis**, Pint, PHPStan 8, pack 18/18, déploiement 7/7, types 54 à jour. Les essais interrompus ou intermédiaires restent documentés dans [B23_DRAFT_READINESS.md](../../../quality/B23_DRAFT_READINESS.md). Réception globale déléguée sur worktree propre ; aucune publication ou approbation humaine annoncée.

### Candidat B24 : Q12 et histoire de revue

Parent `81ac242` repris. Corrections demandées notifiées via outbox transactionnelle, livraison après commit, message/références seuls et droits/visibilité courants avant liste/compteur/marquage. Entier JSON strict, fixture publiée conforme B22 et downgrade refusant perte de snapshots/événements stables. Probes réels 3/13 (deux500) et 1/1 (downgrade sans refus) avant fix ; ciblés 53/496 puis onze ciblés exacts 11/116 recouvrant les précédents, tous verts. Hors SQL 350/3800, Pint/PHPStan8, Composer, documents18/7 et types57 verts. Suite PostgreSQL complète en cours sur base B24 dédiée, résultat non revendiqué ; CI du candidat en brouillon et revue humaine à recevoir. Preuves : [B24_REVIEW_READINESS.md](../../../quality/B24_REVIEW_READINESS.md). Aucun DONE ou acceptation/publication B25.

### Réception complète B23 — candidat `d7ef6eb`

Réception indépendante terminée sur le code applicatif propre `d7ef6eb8c750987ddf509eba6647007162c1bec9`, sans changement de code ni de tests : Unit/Feature/Architecture **343 / 3720**, PostgreSQL **478 / 3734**, soit **821 tests / 7454 assertions uniques réussis**. Base dédiée `haas_b23_review_test`, PostgreSQL 17 local 55447, PHP 8.5.10 ; les deux JUnit ont zéro erreur, échec ou test ignoré. Les ciblés précédents ne s’ajoutent pas à ce total.

Pint, PHPStan niveau 8, validation Composer, exigences de plateforme et audit verrouillé réussis ; pack 18/18, documentation de déploiement 7/7 et 54 types API à jour. Détails et commandes : [B23_DRAFT_READINESS.md](../../../quality/B23_DRAFT_READINESS.md). La CI du candidat est verte ([run 37691657985](https://github.com/haas-projet/haas/actions/runs/37691657985), constat de l’intégrateur) ; le commit documentaire de réception recevra encore sa CI exacte après publication. Revue humaine distincte en attente, aucun DONE ou gate. Publication confiée à l’intégrateur.

### Réception finale B24 — 7 octobre 2026, 22:06 UTC

Code `994de150` inchangé par le merge documentaire `e01202f` du parent B23 final. Suites complètes terminées : **350 tests / 3800 assertions** hors SQL et **524 / 4037** sur `haas_b24_review_test` dédiée, soit **874 / 7837 uniques réussis**. JUnit SQL sans erreur, échec ou test ignoré ; aucune somme de ciblés. Pint, PHPStan 8, validation/plateforme/audit Composer réussis ; contrôles documentaires et 57 types conservés. La [CI exacte e01202f](https://github.com/haas-projet/haas/actions/runs/37693092990) est verte sur PHP 8.4/8.5 avec les mêmes totaux, constat et logs transmis par l’intégrateur. Revue humaine en attente ; bilan documentaire à publier puis CI de son SHA exact à observer. Détails : [B24_REVIEW_READINESS.md](../../../quality/B24_REVIEW_READINESS.md) et [état des PR](../../../quality/PR_READINESS_20261007.md). Aucun DONE, gate ou publication B25.


### Suite de concurrence B23/B24 — tests ajoutés

Patron NotificationConcurrencyTest/IdempotencyConcurrencyTest (Symfony Process + barrière PostgreSQL via `application_name = 'haas_b24_capsule_worker'`) étendu aux écritures des brouillons/revues. `backend/tests/Fixtures/write-capsule-concurrently.php` et `backend/tests/Integration/Capsules/CapsuleWritesConcurrencyTest.php` couvrent quatre scénarios réellement concurrents : (i) deux `POST /capsules` avec la même `Idempotency-Key` écrivent une seule capsule (rejeu via `api_idempotency`) ; (ii) deux PATCH simultanés avec le même `lock_version` → un `UPDATED`, un `409 StaleCapsuleVersion` ; (iii) deux `submit-review` simultanés (acteur unique, clés distinctes) → un seul passage `draft → in_review`, un `AuthorizationException` ; (iv) deux `request-changes` simultanés (même modérateur, clés distinctes) → une seule décision écrite en `capsule_version_reviews`, un `AuthorizationException`. Chaque enfant termine sa transaction propre ; aucun test n'a révélé un double effet ou un défaut dans les Services courants. Trait `CapsuleReviewNotificationFixtures` ajouté pour laisser `migrate:rollback` purger les événements de revue (le downgrade B24 refuse de perdre un événement stable).

Contrôles exécutés depuis la racine `backend/` sous PHP Laragon 8.4.15, base `haas_capsules_test`/`haas_test`, `COMPOSER_PROCESS_TIMEOUT=0` : `composer lint` → `{"tool":"pint","result":"passed"}` ; `composer analyse` → `[OK] No errors` ; `composer test` → `OK (350 tests, 3800 assertions)` en 15,258 s ; `vendor/bin/phpunit --testsuite Integration --filter CapsuleWritesConcurrencyTest` → `OK (4 tests, 36 assertions)` en 12,011 s ; `vendor/bin/phpunit --testsuite Integration` complète → `OK (528 tests, 4073 assertions)` en 12 min 51,171 s. Les mêmes totaux sont attendus côté CI distante, à constater sur le SHA du commit publié. Aucune donnée hors domaine modifiée. Aucun DONE, gate, frontend ou déploiement annoncé.


## 2026-10-08 — Lot B25 · Publier une version immuable

Statut proposé : B25 **préparé, PR en brouillon**, aucun `DONE`. Branche `backend/capsules-laboratoire-b25-publication`, dérivée de `backend/capsules-laboratoire-b24-revue` (`686a093`).

### Décisions clefs (propriétaire du domaine)

- **`content_digest` : nullable `char(64)`** sur `capsule_versions` avec CHECK format hex 64 et CHECK `state IN ('published','withdrawn')`. Le cahier §09 et §13 citent une « empreinte » sans nommer la colonne : lecture restrictive consignée, la colonne n'est jamais écrite hors du Service de publication (RM03).
- **Sérialisation canonique** du digest : SHA-256 d'un JSON `{version_label, body, limits, technologies[trié par technology_id]}` (`JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES`). Décision consignée Q13 à relire par l'intégrateur.
- **Décision `approved`** ajoutée à `ReviewDecision` + migration : enum étendu et `note` devient nullable, CHECK conditionnel (`approved ⇒ note NULL`, autre ⇒ 20-2000 caractères).
- **Immuabilité SQL** : étend `b22_guard_capsule_version` (CREATE OR REPLACE) pour inclure `content_digest` dans la liste des colonnes refusées sur une version publiée/retirée. Pas de nouveau trigger : la garde B22 existait déjà pour `version_label`/`body`/`limits`/`capsule_id`/`reviewer_id`/`published_at`.
- **Pas de notification** sur `approved` (Q12 encore ouverte). **Pas de copie automatique** d'artefacts ou de définitions de laboratoire vers une nouvelle version (RM03 ; AC13 couvert par tests).
- **« Procédure de vérification présente »** (§13) : lu restrictivement comme « limits non vide » (c'est où la portée/procédure est déclarée). Pas de nouvelle colonne dédiée. Question ouverte Q14 pour une colonne distincte si le cahier le réclame plus tard.
- **Resource de publication** : whitelist stricte, aucun `reviewer_id`, aucune note, aucun chemin privé d'artefact.

### Fichiers créés / modifiés

- Enum : `App\Enums\Capsules\ReviewDecision` (ajout de `Approved`).
- Data : `App\Data\Capsules\PublishCommandData` (lock_version seul).
- Policy étendue : `CapsulePolicy::publishVersion` (délègue à `reviewVersion`).
- Service : `App\Services\Capsules\PublishCapsuleVersionService` (transaction, verrou, documentation complète, provenance active, artefact `approved` → `notices_path` obligatoire, calcul du digest, décision `approved`, audit).
- Migrations : `add_content_digest_to_capsule_versions_table`, `allow_approved_review_decision`, `b25_protect_content_digest_on_published_versions`.
- Controller/Request/Resource : `PublishCapsuleVersionController`, `PublishCapsuleVersionRequest`, `CapsuleVersionPublishedResource`.
- Route : `POST /api/v1/admin/capsules/{capsule}/versions/{version}/publish` dans `routes/api/capsules-lab.php`.
- Fragment OpenAPI : nouveau chemin + `CapsuleVersionPublishInput`/`CapsuleVersionPublished`.
- Tests : `CapsulePublishHttpTest` (22 cas) + extension du fixture `write-capsule-concurrently.php` et `CapsuleWritesConcurrencyTest` (+1 cas de publication concurrente).

### Fichiers hors de mon domaine modifiés

- `docs/OPENAPI.yaml` : **+2 lignes** (ajout `$ref` publish). Commit `55b0287`.
- `docs/api/generated/haas-api.d.ts` : **régénéré** par `scripts/generate-api-types.php` (59 types au total, +2). Commit `cbb1f05`.

Aucun autre fichier du socle n'a été touché.

### Contrôles finaux (PHP 8.4.15 Laragon, base `haas_capsules_test`/`haas_test`, `COMPOSER_PROCESS_TIMEOUT=0`)

| Commande | Résultat observé |
|---|---|
| `composer lint` | `{"tool":"pint","result":"passed"}` |
| `composer analyse` | `[OK] No errors` |
| `composer test` | `OK (350 tests, 3839 assertions)` |
| `vendor/bin/phpunit --testsuite Integration --filter CapsulePublishHttpTest` | `OK (22 tests, 53 assertions)` |
| `vendor/bin/phpunit --testsuite Integration --filter CapsuleWritesConcurrencyTest` | `OK (5 tests, 47 assertions)` |
| `vendor/bin/phpunit --testsuite Integration` (suite complète) | en cours à la rédaction ; sera inscrit dans la PR après vérification |

### Commits locaux

- `781be66` schema (digest + approved + note nullable)
- `c49ef3e` trigger (content_digest immuable)
- `04415a7` service + Policy + DTO
- `c266953` route + Controller + Resource + Request
- `589df82` fragment OpenAPI
- `55b0287` `$ref` OPENAPI racine
- `cbb1f05` types régénérés
- `fac3ead` style Pint migration
- `6717f96` fix StoredCommandResult (UUID uniquement)
- `b3c3d71` tests (HTTP + concurrence)

### Questions ouvertes

- **Q13** — Format canonique de `content_digest` : décision du propriétaire du domaine (sérialisation JSON triée par `technology_id`). Lectures alternatives possibles : inclure l'`editorial_origin` ou `slug` dans le payload. À confirmer en revue.
- **Q14** — « Procédure de vérification présente » traitée comme `limits` non vide. Si le cahier exige une colonne distincte, prévoir une migration dédiée.
- Q12 (B24) — Notification « Revue de capsule terminée » reste ouverte pour la décision `approved`.
- Q10, Q11 (B23) et Q2–Q7 (B22) restent ouvertes.

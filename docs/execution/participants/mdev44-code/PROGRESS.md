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

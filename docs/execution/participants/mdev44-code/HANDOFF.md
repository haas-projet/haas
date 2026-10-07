# Reprise — capsules/laboratoire

Branche active : `backend/capsules-laboratoire`. HEAD local après Lot 1 : commit documentaire courant, précédé de `14c4579` (comparisons), `db1ed8d` (lab), `db30cf6` (capsules) et du merge `5e8fc46` de `origin/main`. Rien n'est poussé ni fusionné. Laravel 13.34.0, PHP de session 8.4.15 depuis `C:\laragon\bin\php\php-8.4.15-Win32-vs17-x64` ; le PHP natif 8.4 du PATH `C:\Program Files\php\php.exe` 8.4.3 ne charge pas `openssl` et doit être évité jusqu'à correction. PostgreSQL cible : 17 sur `127.0.0.1:5432`, aligné avec `phpunit.xml`, la CI `backend-ci.yml` et les preuves B01–B06.

Base de test dédiée en place : rôle `haas_test` et base jetable `haas_capsules_test` sur PG 17, mot de passe fourni hors historique dans `~/.haas-test.env`. Charger ce fichier dans la même commande que les tests, sans jamais lire son contenu : `bash -c 'set -a; source ~/.haas-test.env; set +a; cd backend && composer test:integration'`. Les valeurs de `phpunit.xml` priment sur `backend/.env` dans ce processus (PHPUnit pose `DB_*` avant que Laravel ne lise `.env` via Dotenv immutable) ; aucune exécution ne pointe vers `haas_app`. `TestDatabaseGuard` reste la protection : ne pas la contourner.

État courant après Lot 1 :
- B01–B06 intégrés sur `origin/main` par le responsable 1 (PR #4/#5/#6/#7/#8/#9 fusionnées) ; identité, inscription, pagination, rendu d'erreur et CI utilisables en lecture.
- 4 enums de valeurs créés dans mon domaine (`CapsuleVersionState`, `LabRunState`, `ComparisonState`, `ComparisonOutcome`) + table des transitions `CapsuleVersionState` sur l'enum lui-même + 4 tests unitaires.
- Aucun DTO, migration, service, controller, route livré. Aucun fichier interdit touché.
- Tests locaux : `composer test` 148 / 977, `composer test:integration` 35 / 296, Pint et PHPStan niveau 8 verts, audit et validate verts.

Dépendances à surveiller avant mes PR métier ultérieures :
- B11 (schéma collaboration par `LamineGL`) requis par `help_request_id` dans `verification_cases` (BV201) et par l'origine « demande résolue » des capsules (B22).
- B12 (audit) et B13 (idempotence) requis par `StartLabRunService`, `StartComparisonService` et `PublishCapsuleVersionService`.
- B07 (session Sanctum/CSRF/CORS) et B08–B09 (droits, `/me`) requis par toute route métier authentifiée du domaine.
- Point de coordination unique : `HelpIntent`. BV201 est le pilote initial ; `LamineGL` relit, puis BC07 étend avec `ask_question`. Convenir de l'emplacement de l'enum avant d'écrire son fichier.

Transitions `CapsuleVersionState` à arbitrer par le relecteur :
- `in_review → published` : citée indirectement par B25 (`docs/execution/PLAN_COMMITS.md:273`, `docs/execution/tasks.json:296`).
- `published → withdrawn` : citée indirectement par B31 (`docs/execution/tasks.json:361-367`), `docs/product/CAHIER_DES_CHARGES.md:502` et AC15 (`docs/quality/ACCEPTANCE_MATRIX.md:21`).
- Les transitions `draft → withdrawn`, `in_review → withdrawn`, `changes_requested → withdrawn` ne sont pas inscrites : à définir par les Services du lot B31.

Fichiers interdits à ne pas toucher sur cette branche (source : `docs/execution/BACKEND_A_TROIS.md:103`) : `backend/composer.json`, `backend/composer.lock`, `backend/bootstrap/`, `backend/config/`, `backend/.env.example`, `backend/phpunit.xml`, `backend/routes/api.php`, `.github/workflows/`. Dépendances nouvelles : passer par une PR dédiée adressée au responsable du socle.

Procédure de PR (source : `docs/execution/BACKEND_A_TROIS.md:149-155`) :
- Pousser `backend/capsules-laboratoire` seulement sur demande explicite de l'utilisateur ; ouvrir une PR vers `main` avec `Refs #3` dans le corps.
- Relecteur principal : `ousseynoufayeisidk-sys` (source : `docs/execution/BACKEND_A_TROIS.md:11`, colonne « Relecteur principal » du domaine capsules/laboratoire).
- Fusion : commit de merge, pas de rebase d'une branche partagée, pas de force-push, pas de `--no-verify`.
- Si un lot suivant est demandé avant la fusion : préparer sur une branche dérivée séparée depuis le dernier commit, cibler temporairement cette PR, recibler vers `main` après fusion et revérifier la CI du dernier SHA.

Déclaration IA : chaque session Codex/Claude Code qui touche du code applicatif ajoute une ligne dans `docs/AI_USAGE.md` (format de la table ligne 11 : *Outil / intervention | Périmètre | Contrôles | Revue humaine de l'équipe*), avec le modèle réel, le périmètre, les contrôles exécutés et la mention « revue humaine : en attente » tant qu'un collègue n'a pas relu. Les commits portent l'identité Git locale réelle, sans co-auteur IA et sans signature humaine simulée (source : `docs/execution/COMMIT_CONVENTION.md:17`, `docs/execution/BACKEND_A_TROIS.md:153`).

Prochaine reprise : attendre le feu vert pour pousser la branche. En parallèle, sur une branche dérivée séparée si la PR est encore ouverte, préparer `ComparisonOutcomeCalculator` (classe pure, lot BV208) et les scénarios B1 fictifs purs (lot B35, partie unitaire). Les écritures métier dépendantes de B07/B09/B11/B12/B13 restent en attente de leurs prérequis.

## 2026-10-03 — Reprise sur `backend/communaute-entraide-b11-schema` (B11, cession LamineGL)

Nouvelle branche créée depuis `origin/main` (`075e6eb`) : `backend/communaute-entraide-b11-schema`. Elle ne contient **pas** le travail de `backend/capsules-laboratoire` (enums capsules/lab/comparisons de PR #12) ; les deux branches sont indépendantes et peuvent fusionner séparément.

Cession LamineGL → mdev44-code actée par l'utilisateur le 2026-10-03 (voir `CLAUDE.md` local). Je ne reprends **que B11** pour l'instant. B14/B16/B20 ont un rattachement cité mais pas démarré ; le reste (B15, B17, B18, B19, B21, BC01–BC08, BH01–BH10) reste à `LamineGL`.

État courant après B11 :
- Enums `App\Enums\HelpRequests\HelpRequestState` et `App\Enums\Collaboration\ProposalState` posés sans transition (valeurs citées `ARCHITECTURE.md:255-256`).
- 5 migrations convention Laravel, une par table, timestamps `2026_10_03_180000` → `2026_10_03_180400` strictement croissants dans l'ordre des FK (help_requests, request_technologies, proposals, comments, resolutions). **Écart assumé à `BACKEND_A_TROIS.md:110`** sur le nommage sans identifiant de lot (décision de mdev44-code).
- Modèles `HelpRequest`, `Proposal`, `Comment`, `Resolution` ; pivot `request_technologies` sans modèle explicite (aligné sur `user_technologies` B05).
- Factories pour les quatre modèles.
- Tests unitaires d'enums + test d'intégration PostgreSQL `HelpRequestsSchemaTest` (6 cas).
- `IdentityMigrationTest::runIdentityMigration` étendu pour que `B05 down()` fonctionne malgré les FK B11 (modification cross-domain couverte par la cession).

Contrôles verts localement (122 tests / 926 assertions ; 41 tests d'intégration / 310 assertions ; Pint, PHPStan niveau 8, audit, validate). CI distante non observée (aucune PR ouverte).

Convention de nommage des migrations à discuter avec le responsable 1 avant fusion : si refus, renommage trivial `2026_10_03_HHMMSS_b11_create_<table>_table.php` et pose à nouveau. Décision locale consignée dans `CLAUDE.md`.

Procédure de PR identique à Lot 1 : pousser seulement sur demande explicite, PR vers `main` avec `Refs #2` (tâche #2 Lamine) et mention « Responsables consultés : LamineGL (accord de LamineGL du 2026-10-03) ». Relecteur demandé : `LamineGL` d'abord, à défaut `ousseynoufayeisidk-sys`. **Jamais moi.**

Prochaine reprise après B11 fusionnée : revenir sur `backend/capsules-laboratoire` pour B22 (migrations capsules/capsule_versions/capsule_contributors/artifacts, FK `source_help_request_id → help_requests.id` dans une migration séparée après B11 landée), même convention Laravel une par table.

## 2026-10-03 — Après fusion de main et corrections B11

Branche `backend/communaute-entraide-b11-schema` à `12c0d50`, sept commits ahead of `origin/main` (`a051e81`). Merge de main sans conflit (`b152fc1`). B07–B13 et B29/B30/B32 maintenant intégrés sur main.

État du schéma B11 après corrections :
- Zéro CHECK de longueur en base ; `title varchar(140)` et `version_label varchar(40)` seuls à borner la taille. Les règles de longueur du cahier passent par les FormRequests B14+/B17+/B18+ pour permettre les cas particuliers cités (« aucune » pour `attempts`, `help_intent=ask_question` nullables).
- CHECK d'énumération d'état conservés (help_requests, proposals).
- Pas de défaut sur `state` ni en base ni en modèle Eloquent ; `state` retiré des `#[Fillable]`.
- Pivot `request_technologies` en CASCADE sur `request_id` (B05 analogy), RESTRICT sur `technology_id`.
- ResolutionFactory sans `create()` dans `definition()`, closure pour `request_id`.

Contrôles verts localement (253/2454, 141/1266 — compteurs élargis par la fusion de B07–B32). Les anciens compteurs 122/926 + 41/310 appartiennent à la version pré-merge.

Prochaine reprise après B11 fusionnée : B22 sur `backend/capsules-laboratoire`.

## 2026-10-07 — HANDOFF B22 Schéma des capsules

Branche : `backend/capsules-laboratoire-b22-schema`. Base : `backend/capsules-laboratoire` (`71daddf`, qui a déjà fusionné `origin/main` `7a8c672`). Lot livré : **B22 — Schéma des capsules**. Statut proposé : `IN_REVIEW` à l'ouverture d'une PR brouillon ; aucune revue humaine effectuée à ce stade.

### À quelle question ce lot répond

Le schéma SQL des capsules est posé : quatre tables (`capsules`, `capsule_versions`, `capsule_contributors`, `artifacts`) sous les règles de `CAHIER_DES_CHARGES.md:869-872`. Une capsule a toujours exactement une origine (demande résolue XOR origine éditoriale) ; une version par capsule est unique ; un contributeur ne peut pas tenir deux fois le même rôle sur la même version ; un artefact approuvé est unique par (version, digest) sans empêcher l'historique inactif. Les trois objets (capsule, brique facultative, laboratoire facultatif) restent strictement distincts : aucune FK du domaine B22 ne pointe vers les tables `lab_*`, `test_*` ou `demo_*`.

### Dépendances et contrat

- Prérequis fusionné dans `main` : socle B01–B09, B12, B13, B32 ; identité `users` et trait `HasUuids` (User.php:14+32).
- Prérequis fusionné dans la branche de base : enums PR #12 `App\Enums\Capsules\CapsuleVersionState`, `App\Enums\Lab\LabRunState`, `App\Enums\Comparisons\{ComparisonState,ComparisonOutcome}`.
- Prérequis **absent de `main`** : table `help_requests` (branche `backend/communaute-entraide-b11-schema`, auteur `mamylahi`). `capsules.source_request_id` est donc UUID nullable sans FK ; migration de raccord à prévoir après fusion de B11.
- Aucune route ni Service livré ; `docs/OPENAPI.yaml` n'est pas touché.

### Fichiers livrés

- 3 enums `App\Enums\Capsules\` (visibilité, rôle de contribution, statut de distribution).
- 4 migrations `database/migrations/2026_10_07_01*_create_<table>_table.php`.
- 4 modèles Eloquent `App\Models\Capsules\{Capsule,CapsuleVersion,CapsuleContributor,Artifact}`.
- 4 factories `Database\Factories\Capsules\`.
- 3 tests unitaires d'enum + 5 tests d'intégration PostgreSQL (31 tests / 70 assertions au total pour B22).

### Prochaines étapes pour la propriétaire

1. Attendre le feu vert utilisateur avant tout push : `git push -u origin backend/capsules-laboratoire-b22-schema`.
2. Ouvrir une PR brouillon vers `backend/capsules-laboratoire` (branche de base de PR #12) ou directement vers `main` si PR #12 est fusionnée d'ici là. Discuter en revue les six questions ouvertes consignées dans PROGRESS.md (visibilité, rôle, statut, format editorial_origin, FK source_request_id, convention nommage).
3. Observer la CI distante après le push.
4. B23 démarre sur une branche dérivée séparée de B22, sans toucher la PR en revue.

### Fichiers à ne pas toucher jusqu'à nouvel ordre

- `backend/config/*`, `backend/bootstrap/app.php`, `backend/phpunit.xml`, `backend/.env.example`, `backend/composer.*`, `.github/workflows/*`.
- `backend/routes/api.php`, `backend/routes/api/identity.php`, `backend/routes/console.php`.
- `docs/OPENAPI.yaml` (fichier responsable 1 ; B22 n'ajoute aucune route).

### Contrôles réellement exécutés

- `composer lint` **passed**, `composer analyse` **[OK] No errors**.
- `composer test` : **284 tests / 2510 assertions, OK**.
- `composer test:integration` (filtre Capsules|Artifacts) : **31 tests / 70 assertions, OK**.
- `composer test:integration` (suite complète) : **164 tests / 1320 assertions, OK**.
- CI distante NON EXÉCUTÉE à ce stade (branche non poussée).

## 2026-10-07 — HANDOFF B22 (après reprise à l'étape 3)

Branche : `backend/capsules-laboratoire-b22-schema`. Statut proposé à l'ouverture de la PR : **préparé, PR en brouillon**. Aucun `DONE` ne doit être prononcé avant revue humaine et fusion.

Nouveaux faits depuis le premier HANDOFF B22 :

- Merge local `--no-ff` de `origin/backend/communaute-entraide` dans `backend/capsules-laboratoire-b22-schema` (`478d1e0`). Trois fichiers documentaires résolus manuellement (`docs/AI_USAGE.md`, PROGRESS.md et HANDOFF.md du participant) sans reformulation, ordre chronologique préservé. Aucun autre fichier n'a dû être résolu à la main.
- FK posée : `capsules.source_request_id → help_requests` (`restrictOnDelete`, nullable). La contrainte XOR `capsules_source_xor` reste intacte. Q1 fermée.
- Enum `ContributionRole` reconstruit sur le vocabulaire du cahier (`diagnosis, fix, documentation, test, case`). Q4 fermée.
- Nouveau helper `Tests\Support\Mdev44\DomainTables::dropAll()` pour que les tests de migration partagés (`HelpRequestsMigrationTest`, `IdentityMigrationTest`) puissent déposer la chaîne capsule avant les rollbacks B11/B05.

### Fichiers hors de mon domaine modifiés (minimal)

- `backend/tests/Integration/HelpRequests/HelpRequestsMigrationTest.php` : +2 lignes (import + appel `DomainTables::dropAll()`). Raison : la FK `capsules.source_request_id → help_requests` empêche le rollback de B11 sans ce dépôt préalable.
- `backend/tests/Integration/IdentityMigrationTest.php` : +2 lignes (import + appel `DomainTables::dropAll()`). Raison : la même FK, propagée jusqu'à B05 via `help_requests → users`.

### Contrôles finaux

- `composer lint` → `{"tool":"pint","result":"passed"}`.
- `composer analyse` → `[OK] No errors` (PHPStan niveau 8).
- `composer test` → **315 tests / 2541 assertions, OK**.
- `composer test:integration` (suite complète) → **185 tests / 1400 assertions, OK**.
- CI distante : à observer après push.

### Prochaines étapes

1. Pousser la branche et ouvrir une PR brouillon, base `backend/capsules-laboratoire`.
2. Mentionner dans le corps : « contient le merge du schéma B11 », cinq questions ouvertes restantes (Q2, Q3, Q5, Q6, Q7), et les deux fichiers hors de mon domaine modifiés.
3. Attendre revue par un relecteur humain distinct de l'autrice.

Aucun `DONE` prononcé.

## 2026-10-07 — HANDOFF B23 Brouillons de capsule

Branche : `backend/capsules-laboratoire-b23-brouillons`. Base : `backend/capsules-laboratoire-b22-schema` (`58fe552`). Statut proposé à l'ouverture de la PR : **préparé, PR en brouillon**. Aucun `DONE`.

### À quelle question ce lot répond

Un membre vérifié peut créer un brouillon de capsule à partir de sa demande résolue (résolution active), avec titre/résumé/problème/cause/correction/procédure dans `capsule_versions.body` et un pivot technologies déclarant les versions compatibles. Un moderator/admin peut créer un brouillon à origine éditoriale. Le propriétaire d'une capsule peut y ajouter une nouvelle version-brouillon. Deux POST avec la même clé d'idempotence et la même charge produisent une seule capsule et une seule revision d'audit ; charge différente = 409.

### Dépendances et contrat

- Prérequis fusionnés dans la branche de base : socle B01–B09, B12, B13, B32, enums PR #12, schéma B22 (capsules/capsule_versions/capsule_contributors/artifacts), schéma B11 complet (help_requests/proposals/resolutions).
- Aucune route ni Service livré hors de mon domaine. L'audit utilise la table partagée `content_revisions` sans modifier `AuditWriter`.

### Fichiers hors de mon domaine modifiés

- `docs/OPENAPI.yaml` : +4 lignes `$ref` (deux routes B23). Fichier du propriétaire socle 1, modification limitée à l'ajout nécessaire pour `ApiInventoryTest`, par analogie avec la procédure du commit fd82ebc.
- `docs/api/generated/haas-api.d.ts` : régénéré automatiquement par `scripts/generate-api-types.php`. Aucune édition manuelle ; le diff est strictement l'ajout des quatre schémas B23.

### Prochaines étapes

1. Observer la CI distante après `git push -u origin backend/capsules-laboratoire-b23-brouillons`.
2. PR brouillon vers `backend/capsules-laboratoire-b22-schema` (empilée sur la PR #29).
3. Discuter en revue les quatre questions ouvertes B23 (Q8 PATCH, Q9 auteur proposition, Q10 pivot technologies, Q11 header replay) et les cinq questions B22 (Q2 à Q7) encore ouvertes.

## 2026-10-07 — Suite B23 : PATCH, tests HTTP, Q8/Q9 fermées

B23 ajoute la route PATCH, 31 nouveaux tests HTTP (20 POST + 11 PATCH) et ferme Q8 (PATCH livré) et Q9 (auteur de la proposition acceptée autorisé).

### Nouvelles routes exposées

- `PATCH /api/v1/capsules/{capsule}/versions/{version}` (name `capsules.versions.drafts.update`). lock_version obligatoire ; 409 sans mutation si périmé.

### Fichiers hors de mon domaine modifiés (cumul B23)

- `docs/OPENAPI.yaml` : **+6 lignes** cumulées pour les trois routes B23 (POST x2 + PATCH). Commits `5d84bf9` et `6fa306e`.
- `docs/api/generated/haas-api.d.ts` : **régénéré 2 fois** par `scripts/generate-api-types.php`. Commits `60fceb0` et `5935fe6`.

Aucun autre fichier du socle n'a été touché.

### Contrôles finaux

- `composer lint` → `passed`.
- `composer analyse` → `[OK] No errors`.
- `composer test` → **323/2675 OK**.
- `composer test:integration` (suite complète) → **230/1495 OK**.
- CI distante : à observer après push.

### Prochaines étapes

1. Pousser les commits locaux restants vers `origin/backend/capsules-laboratoire-b23-brouillons`.
2. `gh pr edit 30` pour mettre à jour le corps de la PR #30 (nouveaux commits, Q8/Q9 fermées, Q11 clarifiée, Q10 restante).
3. Attendre revue humaine distincte.

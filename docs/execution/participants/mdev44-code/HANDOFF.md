# Reprise — capsules/laboratoire

## 15 octobre 2026 — BV202, création et soumission des cas, PR en brouillon

Branche active : `backend/capsules-laboratoire-bv202-cas-soumission` basée sur BV201 `a660a47`. BV202 **préparé, PR en brouillon**. Prochaine étape : réception CI (règle d'arbitrage : tests verts PHP 8.4 et PHP 8.5, seule l'étape « Contrôler les dépendances » tolérée en rouge pour les deux CVE externes) avant de brancher BV203 depuis la tête BV202.

Routes livrées (toutes alignées sur les noms de B23–B34, préfixe `verification-cases`) :
- `GET` / `POST /verification-cases`
- `GET` / `PATCH /verification-cases/{verification_case}` (lock_version + Idempotency-Key)
- `POST /verification-cases/{verification_case}/submit`
- `POST /verification-cases/{verification_case}/withdraw`

Fichiers livrés (hors tests) :
- Routes : `backend/routes/api/capsules-lab.php` (6 routes).
- DTO/Enum : `backend/app/Data/VerificationCases/{CreateVerificationCaseData,ReviseVerificationCaseData,SubmitVerificationCaseData,WithdrawVerificationCaseData,VerificationCaseParent}.php`.
- Controllers : `backend/app/Http/Controllers/VerificationCases/*.php` (6).
- FormRequests : `backend/app/Http/Requests/VerificationCases/*.php` (4).
- Services : `backend/app/Services/VerificationCases/{CreateVerificationCaseService,ReviseVerificationCaseService,SubmitVerificationCaseService,WithdrawVerificationCaseService,WriteVerificationCaseAudit}.php`.
- Policy : `backend/app/Policies/VerificationCases/VerificationCasePolicy.php` (propriété seule ; état revérifié par les Services pour tolérer les rejeux).
- Resource : `backend/app/Http/Resources/VerificationCases/VerificationCaseResource.php` (corps du brouillon réservé à l'auteur ; révisions figées visibles pour tout acteur habilité).
- Query : `backend/app/Queries/VerificationCases/VisibleVerificationCasesQuery.php`.
- Support : `backend/app/Support/VerificationCases/{VerificationCaseBody,VerificationCaseParentVisibility}.php`.
- Exceptions : `backend/app/Exceptions/VerificationCases/{StaleVerificationCase,VerificationCaseNotFound}.php`.
- OpenAPI : fragment `docs/api/openapi/verification-cases.yaml` + 4 `$ref` dans `docs/OPENAPI.yaml` + types régénérés `docs/api/generated/haas-api.d.ts`.
- Tests : `backend/tests/Integration/VerificationCases/VerificationCaseHttpTest.php` (14 scénarios HTTP), `backend/tests/Integration/VerificationCases/VerificationCaseConcurrencyTest.php` (deux processus PATCH concurrents sur `lockForUpdate`, barrière `pg_stat_activity`), `backend/tests/Fixtures/revise-verification-case-concurrently.php`.

Non modifiés : `backend/routes/api.php`, `backend/config/*`, `backend/bootstrap/*`, `backend/phpunit.xml`, `composer.*`, `.github/workflows/*`, les contraintes des tables de notification. Le fichier `backend/app/Services/Idempotency/IdempotencyService.php` est resté inchangé (débogage temporaire retiré).

PHP de session utilisé pour toutes les commandes PHP : `C:\laragon\bin\php\php-8.4.15-Win32-vs17-x64\php.exe` (`openssl`, `pdo_pgsql` chargés). `~/.haas-test.env` jamais lu ; aucune action manuelle sur PostgreSQL.

CI attendue : tests verts PHP 8.4/PHP 8.5 ; étape d'audit composer rouge pour avis externes `symfony/http-foundation` CVE-2026-108082 et `symfony/yaml` CVE-2026-109413 ; `composer.lock` à mettre à jour par le responsable du socle.

## 15 octobre 2026 — BV201, schéma des cas, PR en brouillon

Branche active : `backend/capsules-laboratoire-bv201-schema-cas` basée sur `backend/capsules-laboratoire-b34-lancement-lab` vert `0a5cb95`. BV201 **préparé, PR en brouillon**. Prochaine étape : réception CI verte avant de brancher BV202 depuis la tête de BV201.

Fichiers livrés :
- `backend/database/migrations/2026_10_15_120000_bv201_create_verification_cases_table.php`
- `backend/database/migrations/2026_10_15_120100_bv201_create_verification_case_revisions_table.php`
- `backend/app/Enums/VerificationCases/VerificationCaseState.php`
- `backend/app/Models/VerificationCases/VerificationCase.php`
- `backend/app/Models/VerificationCases/VerificationCaseRevision.php`
- `backend/database/factories/VerificationCases/VerificationCaseFactory.php`
- `backend/database/factories/VerificationCases/VerificationCaseRevisionFactory.php`
- `backend/tests/Integration/VerificationCases/VerificationCasesSchemaTest.php`
- `backend/tests/Integration/VerificationCases/VerificationCasesMigrationTest.php`
- `backend/tests/Support/Mdev44/DomainTables.php` (étendu, enfants d'abord)
- `backend/tests/Integration/Capsules/CapsulesMigrationTest.php` (rollback étendu)

Non modifiés : `backend/routes/api.php`, `backend/config/*`, `backend/bootstrap/*`, `backend/phpunit.xml`, `composer.*`, `.github/workflows/*`, les contraintes des tables de notification. L'enum `HelpIntent` et `help_requests.help_intent` déjà introduits par B14 (`backend/database/migrations/2026_10_04_000014_b14_extend_help_request_content.php:18`, `backend/app/Enums/HelpRequests/HelpIntent.php:6`) ne sont pas touchés.

Local non exécuté : PHP du PATH sans extensions ; aucune action sur PostgreSQL ni lecture de `~/.haas-test.env`. La CI backend-ci arbitre après push ; la relance s'opère via `gh pr checks --watch` sur la PR BV201.

## 12 octobre 2026 — Réception propre du registre sur B25

Lot B33 isolé depuis le candidat historique `04385b6573f3c6d80a71dde6d4e1fce3fbe25449`, sur B25 `365e549b2be2d9f2fedb557e8309213b182bfbaa`. Ordre **B33 → B26 → B27**, aucun cycle de prérequis. Code de registre conservé, harness de test rendu indépendant du wrapper local.

**439 tests /4215 assertions hors SQL +63 /392 SQL ciblés =502 /4607 reçus**, zéro erreur/échec/skip. Vraie liaison à deux processus1/11 incluse, sans double comptage. PHPStan8 sans erreur, Pint global, **64 types** reçus sur cette base. Base locale exclusive `haas_b33p25_review_test`, PostgreSQL17, 127.0.0.1:55447, runnerb33p25 ; composer.lock inchangé. Globale Integration locale et CI exacte à recevoir ; B33 **IN_PROGRESS** avant cette réception et revue humaine. Aucun gate, frontend ou déploiement.

[Commandes et incidents](../../../quality/B33_LAB_REGISTRY_20261012.md). Préparation assistée par Codex, sans identité ou approbation humaine attribuée au pilote. Historique ci-dessous conservé.


## 12 octobre 2026 — B33, registre reçu sur ciblés

B33 **IN_PROGRESS**, code local depuis B27 figé : 480/4437 hors SQL +62/375 SQL ciblés, liaison concurrente réelle incluse, PHPStan8/Pint/70 types reçus. Report isolé sur B25 nécessaire pour l’ordre B33 → B26 → B27, puis réception générale/SQL complète/CI exacte et revue humaine. Les quatorze fichiers B1 épinglés sont identiques sur les deux bases. Aucun run lancé par le registre, switch fermé, B34/B36 à réaliser ; aucun gate, frontend ou déploiement. [Preuves et limites](../../../quality/B33_LAB_REGISTRY_20261012.md). Préparation Codex, aucune identité ou approbation humaine attribuée au pilote.

Entrées suivantes historiques.


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

## 2026-10-07 — HANDOFF B24 Soumettre à la revue

Branche : `backend/capsules-laboratoire-b24-revue`. Base : `backend/capsules-laboratoire-b23-brouillons` (`93fafea`). Statut proposé : **préparé, PR en brouillon**. Aucun `DONE`.

### Nouvelles routes

- `POST /api/v1/capsules/{capsule}/versions/{version}/submit-review`.
- `POST /api/v1/admin/capsules/{capsule}/versions/{version}/request-changes`.

### Fichiers hors de mon domaine modifiés (B24)

- `docs/OPENAPI.yaml` : +4 lignes `$ref`.
- `docs/api/generated/haas-api.d.ts` : régénéré.

### Contrôles finaux

- `composer lint` passed ; `composer analyse` OK ; `composer test` 323/2723 ; `vendor/bin/phpunit --testsuite Integration` 248/1523.
- CI distante : à observer après push.

### Question Q12 bloquante pour B25

La notification « Revue de capsule terminée » (CAHIER_DES_CHARGES.md:355) nécessite d'étendre `App\Data\Notifications\NotificationEvent` qui limite `kind` à `profile.moderated` (fichier du socle, hors de mon domaine). Décision à prendre avant B25.

## 2026-10-07 — Correctif de préparation Codex

Revue indépendante conservée, mêmes frontières B24/B25. Nouveaux verrous/intention/rejeu, refus des champs inconnus, notes Unicode/multilignes, projection privée, snapshot versionné et conservation SQL append-only des décisions. Une panne SQL d'audit réelle annule aussi bien la soumission que la demande de corrections ; upgrade/rollback conserve les notes antérieures. Six courses entre deux processus PostgreSQL réellement bloqués couvrent les conflits et les droits modifiés.

Preuves locales : **33 tests / 162 assertions** domaine puis **23 / 90** compléments, types **36**, documents **18 + 7** ; voir `docs/quality/B24_REVIEW_READINESS.md`. Les trois correctifs de tests cookies B17 `8234470` sont repris ponctuellement et la règle de secrets existante est copiée sans dépendance nouvelle.

Q12 concerne déjà les corrections B24 selon le cahier :355. Le contrat main B17 fournit maintenant `NotificationOutbox`; l'intégrateur a réservé ses fichiers communs pour le raccord B24. Après ce premier commit propre, intégrer B23/B22/main, raccorder la notification générique sans note, tester droits/visibilité et suites complètes. Aucun push/merge/ready effectué par cet agent, aucune attribution de revue humaine.

## 2026-10-07 — Correctif de préparation Codex

Reprise de la PR #30 sans réécrire les décisions Q8/Q9 ni leurs suivis. Voir `docs/quality/B23_DRAFT_READINESS.md` : création depuis une résolution cohérente et verrouillée, conflits 409, présence de limites nulles conservée, droits actuels sous verrous, résultat idempotent limité à sa version, validation stricte et six courses réelles. Services compatibles avec les `Fillable` serveur restreints de B22 par `forceFill` explicite.

Le socle commun reçoit seulement la règle `NoLikelySecret` existante, les trois correctifs de tests de cookies relus du B17 `8234470` et le PHPDoc du cast enum existant de `HelpRequest.state`. Les types API sont régénérés par script. Aucun secret ni vendor ajouté au suivi Git.

Contrôles observés : ciblés **13 / 111**, suite capsules **92 / 290**, Unit/Feature/Architecture **323 / 2675**, PHPStan sans erreur, Pint passé, documents **18 + 7**, types **33**. Suite complète combinée après intégration B22/main, CI et revue humaine restent nécessaires. Aucun push ou merge effectué par cet agent ; publication gérée par l'intégrateur. SHA du commit local à consigner dans son bilan après création.

Complément sur le parent combiné `94aeae9` : source masquée refusée sous verrou et au rejeu, trois régressions et septième course réelle ; fixture publiée conforme B22. Ciblés **28 tests / 158 assertions**, Pint/PHPStan verts. La suite SQL partielle a été interrompue et n'est pas déclarée réussie. Reprendre ce correctif dans B24 puis relancer les suites complètes avant CI et revue humaine.

## 2026-10-05 — Reprise B35 conservée avant intégration

Instantané de la branche B35 ; les instructions ci-dessous décrivent son état historique. L'état d'intégration courant figure dans docs/execution/HANDOFF.md.

Branche active : `backend/capsules-laboratoire-b35-brique-b1` (dérivée de `origin/backend/capsules-laboratoire`). La branche parent `backend/capsules-laboratoire` porte le lot 1 enums ; la PR #12 y est ouverte sur `main`. Laravel 13.34.0, PHP de session 8.4.15 depuis `C:\laragon\bin\php\php-8.4.15-Win32-vs17-x64` ; le PHP natif 8.4 du PATH `C:\Program Files\php\php.exe` 8.4.3 ne charge ni `openssl` ni `pdo_pgsql` et doit être évité. PostgreSQL cible : 17 sur `127.0.0.1:5432`, aligné avec `phpunit.xml`, la CI `backend-ci.yml` et les preuves B01–B06.

Base de test dédiée en place : rôle `haas_test` et base jetable `haas_capsules_test` sur PG 17, mot de passe fourni hors historique dans `~/.haas-test.env`. Charger ce fichier dans la même commande que les tests, sans jamais lire son contenu : `bash -c '( cd backend; set -a; source ~/.haas-test.env; set +a; PATH=/c/laragon/bin/php/php-8.4.15-Win32-vs17-x64:$PATH composer test:integration )'`. Attention : `source ~/.haas-test.env` seul ne suffit pas car le fichier n'exporte pas forcément les variables ; `set -a` encadre ce chargement et exporte toutes les affectations vers le subprocess Composer/PHPUnit. Sans cela, PHPUnit retombe sur les défauts de `phpunit.xml` (base `haas_bootstrap_test`, mot de passe vide) et échoue avec `fe_sendauth: no password supplied`. Les valeurs de `phpunit.xml` priment sur `backend/.env` dans ce processus (PHPUnit pose `DB_*` avant que Laravel ne lise `.env` via Dotenv immutable) ; aucune exécution ne pointe vers `haas_app`. `TestDatabaseGuard` reste la protection : ne pas la contourner.

État courant après Lot 2 :
- B01–B06 intégrés sur `origin/main` par le responsable 1 (PR #4/#5/#6/#7/#8/#9 fusionnées) ; identité, inscription, pagination, rendu d'erreur et CI utilisables en lecture.
- B11 (schéma collaboration) toujours en revue sur `backend/communaute-entraide` ; propriété du schéma reprise par `mdev44-code` (accord de `LamineGL` du 2026-10-03).
- Lot 1 (branche parent) : 4 enums de valeurs + 4 tests unitaires ; PR #12 ouverte sur `main`.
- Lot 2 (branche dérivée actuelle) : brique B1 complète (B35) — 2 migrations `test_events`/`test_orders`, 3 fichiers Models (dont le point de bascule `LabConnection`), 2 factories, 2 DTO (`TestEventData`, `ProcessedTestEvent`), 1 service `ProcessTestEventService`, 3 tests Integration + 1 fixture subprocess.
- Aucun fichier interdit touché, aucun fichier d'un autre pilote modifié.
- Chiffres de contrôles : voir `PROGRESS.md` section Lot 2 (tables *Contrôles exécutés* et *Décomposition par classe B35*).

Dépendances à surveiller avant mes PR métier ultérieures :
- B11 (schéma collaboration) requis par `help_request_id` dans `verification_cases` (BV201) et par l'origine « demande résolue » des capsules (B22). Statut à vérifier dans `docs/execution/PROGRESS.md` et `docs/execution/participants/*/PROGRESS.md` avant chaque PR dépendante.
- B12 (audit) et B13 (idempotence) requis par `StartLabRunService`, `StartComparisonService` et `PublishCapsuleVersionService`. B35 (brique B1) **n'en dépend pas** : le service transactionnel utilise directement la contrainte unique PostgreSQL `(run_id, event_id)` comme garde d'idempotence.
- B07 (session Sanctum/CSRF/CORS) et B08–B09 (droits, `/me`) requis par toute route métier authentifiée du domaine. B35 n'expose pas de route HTTP ; aucune dépendance ici.
- Connexion `haas_lab` à câbler de concert : changer `App\Models\Lab\LabConnection::NAME` côté domaine, ajouter la connexion `lab` dans `backend/config/database.php`, étendre `backend/phpunit.xml` et la CI côté responsable 1, aligner la connexion secondaire de nettoyage et la configuration `RefreshDatabase` côté tests. `run_id` reste un identifiant logique sans FK (bases `haas_app` et `haas_lab` distinctes).
- Point de coordination unique : `HelpIntent`. BV201 est le pilote initial ; `LamineGL` relit, puis BC07 étend avec `ask_question`. Convenir de l'emplacement de l'enum avant d'écrire son fichier.

Transitions `CapsuleVersionState` à arbitrer par le relecteur :
- `in_review → published` : citée indirectement par B25 (`docs/execution/PLAN_COMMITS.md:273`, `docs/execution/tasks.json:296`).
- `published → withdrawn` : citée indirectement par B31 (`docs/execution/tasks.json:361-367`), `docs/product/CAHIER_DES_CHARGES.md:502` et AC15 (`docs/quality/ACCEPTANCE_MATRIX.md:21`).
- Les transitions `draft → withdrawn`, `in_review → withdrawn`, `changes_requested → withdrawn` ne sont pas inscrites : à définir par les Services du lot B31.

Fichiers interdits à ne pas toucher sur cette branche (source : `docs/execution/BACKEND_A_TROIS.md:103`) : `backend/composer.json`, `backend/composer.lock`, `backend/bootstrap/`, `backend/config/`, `backend/.env.example`, `backend/phpunit.xml`, `backend/routes/api.php`, `.github/workflows/`. Dépendances nouvelles : passer par une PR dédiée adressée au responsable du socle.

Procédure de PR (source : `docs/execution/BACKEND_A_TROIS.md:149-155`) :
- Pousser `backend/capsules-laboratoire-b35-brique-b1` seulement sur demande explicite de l'utilisateur ; ouvrir une PR qui cible temporairement `backend/capsules-laboratoire` (pas `main`), avec `Refs #3` dans le corps (jamais `Closes`).
- Recibler cette PR vers `main` après la fusion de la PR #12 du lot 1 enums, puis revérifier la CI du dernier SHA.
- Relecteur principal : `ousseynoufayeisidk-sys` (source : `docs/execution/BACKEND_A_TROIS.md:11`, colonne « Relecteur principal » du domaine capsules/laboratoire).
- Fusion : commit de merge, pas de rebase d'une branche partagée, pas de force-push, pas de `--no-verify`.
- Si un lot suivant est demandé avant la fusion : préparer sur une nouvelle branche dérivée séparée depuis le dernier commit, cibler temporairement cette PR, recibler vers `main` après fusion et revérifier la CI du dernier SHA.

Déclaration IA : chaque session Codex/Claude Code qui touche du code applicatif ajoute une ligne dans `docs/AI_USAGE.md` (format de la table ligne 11 : *Outil / intervention | Périmètre | Contrôles | Revue humaine de l'équipe*), avec le modèle réel, le périmètre, les contrôles exécutés et la mention « revue humaine : en attente » tant qu'un collègue n'a pas relu. Les commits portent l'identité Git locale réelle, sans co-auteur IA et sans signature humaine simulée (source : `docs/execution/COMMIT_CONVENTION.md:17`, `docs/execution/BACKEND_A_TROIS.md:153`).

Prochaines actions, sur décision humaine : pousser `backend/capsules-laboratoire-b35-brique-b1`, ouvrir la PR ciblant `backend/capsules-laboratoire` (à recibler vers `main` après la fusion de la PR #12 du lot 1), puis demander la revue à `ousseynoufayeisidk-sys` et lui soumettre la demande de câblage de la connexion `haas_lab` dans `config/`, `phpunit.xml` et la CI. Les lots encore à démarrer :
- `ComparisonOutcomeCalculator` (classe pure BV208) : toujours indépendant, peut avancer en parallèle sur une nouvelle branche dérivée.
- B38 (API démonstration B2) : standalone, cookie-free, hors `.haas.example.com` — démarrable sur branche dérivée.
- B22 (schéma capsules) : attend la fusion de B11 pour la FK `source help_request`.
- B33 (registre lab) : attend B22 pour le lien capsule↔lab facultatif.
- BV201 (schéma cas + `help_intent`) : attend B11, coordination `LamineGL` obligatoire avant d'écrire sur `help_requests`.
- B36 (worker canal Unix) : attend B33/B34 **et** une plateforme compatible — PHP Windows (bare 8.4.3 et Laragon 8.4.15 confirmés) n'enregistre pas le transport `unix://` ; les tests réels devront passer par la CI Linux `ubuntu-24.04` du workflow `backend-ci.yml` ou via WSL2 (décision humaine).

## 2026-10-07 — B22 : corrections avant revue humaine

À la demande de l’utilisateur, Codex prépare la PR #29 sans effacer les preuves antérieures. Main `e3bd34c` intégré par merge normal `39d923c` ; conflits documentaires résolus en conservant les historiques. Date de publication/retrait corrigée dans une migration additive ; versions publiées, provenance, attributions et technologies protégées en SQL ; relecteur indépendant et FK RESTRICT ; lock positif et technologies déclarées ; champs serveur non mass assignables. Les migrations B23 de pivot et de verrou sont reprises sous leurs noms existants, sans doublon. Aucune dépendance ni route B22.

Preuves, commandes exactes, incidents de fixtures et limites : [B22_CAPSULE_SCHEMA.md](../../../quality/B22_CAPSULE_SCHEMA.md). Contrat : [CAPSULE_DATA.md](../../../architecture/CAPSULE_DATA.md). Hors SQL 319 / 2570 et SQL 232 / 1587 réussis, soit 551 tests / 4157 assertions uniques ; Pint, PHPStan 8 et Composer réussis. Base dédiée `haas_b22_review_test` sur PostgreSQL 17 local 55447 ; correctif de cookies simulés B17 repris sans assouplissement de production.

Statut : prêt localement pour la revue humaine, CI du SHA publié et revue humaine en attente ; aucun DONE ou BACKEND_GATE. Après contrôles et publication par l’intégrateur, examiner B22 puis intégrer son schéma dans B23/B24 et refaire les tests des consommateurs. Aucun push effectué par cet agent.

### Revalidation B22 avec main B14–B17 intégré

Après le correctif local `2148322`, l’intégrateur prépare un merge normal de main `b76612d1b6587119127fd364244f5248f05f1ff2`. Tests du code combiné réellement exécutés sur la base dédiée `haas_b22_review_test` (PostgreSQL 17, 55447) : hors SQL **335 / 3595**, SQL **399 / 3390**, soit **734 tests / 6985 assertions uniques réussis** ; Pint et PHPStan niveau 8 réussis. Les anciens 551 / 4157 restent une preuve historique et ne s’ajoutent pas à ce total. Journaux/JUnit ignorés dans `backend/storage/logs/b22-main-integration.*`. Détails : [B22_CAPSULE_SCHEMA.md](../../../quality/B22_CAPSULE_SCHEMA.md).

Le conflit du test de migration d’identité conserve les retraits des tables de capsules et des révisions communautaires. Aucun changement d’autorisation ou assouplissement de test. L’intégrateur consigne le SHA réel après création du merge puis publie la branche ; CI du SHA publié et revue humaine toujours à recevoir. Aucun DONE B22, frontend ou déploiement annoncé.

### Transfert de réception B23

Les trois défauts de dernière revue sont reproduits avant correction (13 / 55, dix échecs), puis corrigés. Ciblés définitifs : **24 / 186 réussis**, dont dix courses PostgreSQL réelles ; Pint, PHPStan niveau 8, documents 18/18 et 7/7, types API 54 à jour. Base dédiée `haas_b23_review_test`, PostgreSQL 17 local 55447. Réception globale à relancer du commit transmis par l’intégrateur, sans ajouter les essais précédents au total final. Details : [B23_DRAFT_READINESS.md](../../../quality/B23_DRAFT_READINESS.md). B24 doit reprendre ce parent avant sa réception ; aucun push, frontend ou revue humaine inventée.

### Candidat B24 après reprise du parent B23

Q12 corrections demandées et préservation de l’attribution raccordées, aucun B25. Hors SQL350/3800, ciblés53/496 puis onze ciblés exacts11/116 verts (recouvrement), Pint/PHPStan8/Composer/documents18+7/types57 verts. Suite complète Integration en cours sur `haas_b24_review_test`, journal/JUnit ignorés `backend/storage/logs/b24-q12-integration.*` ; aucun résultat complet encore acquis. L’intégrateur peut publier ce candidat en brouillon pour CI parallèle PHP8.4/8.5 ; réception SQL complète et CI du SHA final indispensables avant ready. Détails : [B24_REVIEW_READINESS.md](../../../quality/B24_REVIEW_READINESS.md). Revue humaine en attente.

### Réception B23 terminée localement — candidat `d7ef6eb`

Code transféré propre `d7ef6eb8c750987ddf509eba6647007162c1bec9`, réception indépendante en lecture seule du métier puis suites complètes : **343 tests / 3720 assertions** Unit/Feature/Architecture et **478 / 3734** PostgreSQL, soit **821 / 7454 uniques réussis**. Les ciblés ne sont pas ajoutés et les essais interrompus restent historiques. PHP 8.5.10, PostgreSQL 17 sur `127.0.0.1:55447`, `haas_b23_review_test`/`haas_test` exclusivement ; XML ignorés sous `backend/storage/logs/b23-reception-*`.

Pint, PHPStan 8, les trois contrôles Composer, documents 18/18 et 7/7, types 54 réussis. Aucun code applicatif ni test modifié dans ce lot documentaire. Preuve détaillée : [B23_DRAFT_READINESS.md](../../../quality/B23_DRAFT_READINESS.md). CI candidat verte, [run 37691657985](https://github.com/haas-projet/haas/actions/runs/37691657985), constat transmis par l’intégrateur. À reprendre : publication du commit de preuve et observation de sa CI exacte par l’intégrateur, puis revue humaine après prérequis ; B24 conserve sa propre base et sa propre réception. Aucun push par cet agent, aucune approbation humaine, gate ou publication de production annoncée.

### Transfert final B24 — 7 octobre 2026, 22:06 UTC

Réception locale complète sur le code `994de150`, identique dans `backend/` au merge documentaire `e01202f` : **350 / 3800** hors SQL + **524 / 4037** PostgreSQL = **874 tests / 7837 assertions uniques**, exit 0, JUnit SQL sans erreur, échec ou skip. Base dédiée `haas_b24_review_test`, PG17 local 55447 / `haas_test`, PHP 8.5.10 ; aucun test actif après ce résultat. Pint, PHPStan 8, Composer validation/plateforme/audit réussis. [CI exacte e01202f](https://github.com/haas-projet/haas/actions/runs/37693092990) PHP 8.4/8.5/backend-ci verte avec les mêmes totaux, logs lus par l’intégrateur. Les incidents et résultats intermédiaires restent dans [B24_REVIEW_READINESS.md](../../../quality/B24_REVIEW_READINESS.md).

À reprendre par l’intégrateur : publier ce commit documentaire propre, observer sa CI exacte, mettre #33 prête à revue, puis revue humaine dans l’ordre #29 → #30 → #33 ; #28 indépendante. Statuts/heads/runs dans [PR_READINESS_20261007.md](../../../quality/PR_READINESS_20261007.md). Le SHA réel sera transmis après création du commit ; aucun push par cet agent ni approbation inventée. Q12 corrections livrée, acceptation/publication B25 ultérieure ; aucun DONE, gate, frontend ou déploiement.


### Compléments B24 — tests de concurrence réels (parent `1fce2ad`)

Ajout `backend/tests/Fixtures/write-capsule-concurrently.php` + `backend/tests/Integration/Capsules/CapsuleWritesConcurrencyTest.php` empilés sur la PR #33. Patron Symfony Process + barrière PostgreSQL (`application_name = 'haas_b24_capsule_worker'`) comme `IdempotencyConcurrencyTest` et `ModerationConcurrencyTest` ; `pcntl` non utilisé (Windows). Scénarios : POST idempotent, PATCH `lock_version` périmé, soumission concurrente (acteur unique, clés différentes) et `request-changes` concurrente (modérateur unique, clés différentes). Les deux processus enfants attendent réellement un verrou PostgreSQL que le parent détient avant de libérer la course ; aucun scénario n'a mis au jour une double écriture ou un défaut dans les Services courants. Trait `CapsuleReviewNotificationFixtures` repris pour que `migrate:rollback` passe malgré le downgrade protecteur du lot B24. `composer lint`/`composer analyse`/`composer test` verts (350 / 3800) ; `Integration` complète réelle : **528 tests / 4073 assertions** en 12 min 51 s. CI du commit publié à inscrire dans la revue après observation.

Fichiers hors de mon domaine modifiés : aucun. Aucun test, Service, migration ou document du socle touché. Prochaines étapes : vérifier le résultat réel de la suite Integration en cours, pousser la branche `backend/capsules-laboratoire-b24-revue`, actualiser le corps de la PR #33 et attendre la CI complète avant d'ouvrir la PR B25 depuis cette branche.

## 2026-10-08 — HANDOFF B25 Publier une version immuable

Branche `backend/capsules-laboratoire-b25-publication` dérivée de B24 (`686a093`). Préparé, PR en brouillon, aucun DONE.

### Résumé du comportement livré

- `POST /api/v1/admin/capsules/{capsule}/versions/{version}/publish` : `in_review → published` par un modérateur/admin ni owner ni contributeur (AC12). `lock_version` + `Idempotency-Key` obligatoires ; champs inconnus refusés en 422.
- Contrôles atomiques dans la transaction : documentation complète (body ≥ 20 non blancs + limits non vide), résolution source toujours active (si demande d'aide), chaque artefact `approved` → `notices_path` renseigné (`distribution_status` jamais modifié).
- Écrit `state=published`, `reviewer_id`, `published_at` serveur, `content_digest` SHA-256 d'un payload canonique (`version_label`, `body`, `limits`, `technologies[trié par id]`), décision `approved` dans `capsule_version_reviews` sans note, audit. Pas de notification (Q12 encore ouverte pour `approved`).
- Trigger SQL `b22_guard_capsule_version` étendu à `content_digest` : toute mutation d'une version publiée est refusée par la base (AC13, RM03).

### Fichiers hors de mon domaine modifiés (B25)

- `docs/OPENAPI.yaml` : **+2 lignes** (`$ref` publish). Commit `55b0287`.
- `docs/api/generated/haas-api.d.ts` : **régénéré** par le script (`cbb1f05`).

Aucun autre fichier du socle n'a été touché.

### Preuves locales exactes (PHP 8.4.15 Laragon, base dédiée `haas_capsules_test`/`haas_test`)

| Commande | Résultat observé |
|---|---|
| `composer lint` | `{"tool":"pint","result":"passed"}` |
| `composer analyse` | `[OK] No errors` |
| `composer test` | `OK (350 tests, 3839 assertions)` |
| `vendor/bin/phpunit --testsuite Integration --filter CapsulePublishHttpTest` | `OK (22 tests, 53 assertions)` |
| `vendor/bin/phpunit --testsuite Integration --filter CapsuleWritesConcurrencyTest` | `OK (5 tests, 47 assertions)` |
| `vendor/bin/phpunit --testsuite Integration` (suite entière) | `OK (551 tests, 4137 assertions)` en 8 min 00 s |

### Décisions du propriétaire du domaine à relire

- Format canonique du `content_digest` : JSON `{version_label, body, limits, technologies[trié]}` SHA-256. Alternatives possibles : inclure `editorial_origin`/`slug`. **Q13** ouverte.
- Contrôle de présence de la procédure NON implémenté : le schéma n'a pas de champ dédié (corps libre et limites). Le Service exige un corps d'au moins 20 caractères non blancs et des limites non vides ; la présence de la procédure dans le corps est jugée par le relecteur humain. **Q14** ouverte.
- Trigger SQL livré en étendant `b22_guard_capsule_version` (CREATE OR REPLACE), **pas** de nouveau trigger. La suite PostgreSQL de tests passe sous `DomainTables::dropAll()` en tearDown (`CapsulePublishHttpTest`) pour laisser `migrate:rollback` passer malgré les downgrades protecteurs B22/B24/B25.

### À reprendre

- Observer la CI distante sur le SHA de la PR brouillon ; mettre à jour cette entrée après résultat.
- Décider en revue : Q13 (digest), Q14 (procédure), Q12 (notification `approved`).
- B26 commence depuis cette branche seulement si la CI B25 est verte.

## Historique de la branche B38 avant intégration

# HANDOFF — capsules/laboratoire (branche B38)

Branche : `backend/capsules-laboratoire-b38-api-demo-b2`. Base : `origin/main` (`7a8c672`). Lot livré : **B38 — API de démonstration B2**. Statut proposé : `IN_REVIEW` à l’ouverture de la PR brouillon ; aucune revue humaine effectuée à ce stade.

## À quelle question ce lot répond

Deux envois d’un formulaire B2 avec la même clé stable (`Idempotency-Key`, UUID v4) et la même charge donnent une seule commande fictive. Une charge différente avec la même clé est rejetée. Aucune session HAAS, aucun cookie, aucune donnée métier HAAS n’est touché. Les commandes fictives sont bornées en durée par la commande Artisan `demo:prune` (rétention 24 h par défaut).

## Dépendances et contrat

- Prérequis fusionné dans `origin/main` : B13 Idempotence (`App\Data\Idempotency\IdempotencyKey`, `IdempotencyData`, `ApiExceptionRenderer` qui mappe `IdempotencyConflict` → 409 `IDEMPOTENCY_CONFLICT`).
- Aucun dépendance aux PR #12 (enums capsules/lab/comparaisons), #22, #23, #24, #25, #26, #27.
- Branche `backend/capsules-laboratoire-b35-brique-b1` (PR #27) non consommée. Elle introduit un pattern similaire (`LabConnection::NAME = null`) qui a inspiré `DemoConnection`.

## Route livrée

- `POST /api/v1/b2/demo-orders` (name: `demo.orders.record`).
- Entrée : header `Idempotency-Key` (UUID v4) + body `{order_ref, amount_minor, currency}`.
- Réponses : 201 création, 200 rejeu (header `X-Idempotent-Replay`), 409 conflit, 422 validation.
- Aucun `Set-Cookie`, aucune session démarrée (contrôle explicite dans les tests).

## Commande Artisan livrée

- `demo:prune [--older-than=<secondes>]` : supprime au plus 1000 commandes fictives expirées par invocation ; sortie limitée au nombre de lignes retirées. Rétention par défaut 24 h (`PurgeExpiredDemoOrdersService::DEFAULT_RETENTION_SECONDS`), bornée à 30 j max.
- Portée stricte : seule la table `demo_orders` sur `DemoConnection::NAME` est touchée.
- Planification à poser par le responsable 1 dans `backend/routes/console.php` ; diff proposé dans `docs/quality/B38_B2_API.md` et dans la PR.

## Prochaines étapes pour la propriétaire

1. Pousser la branche : `git push -u origin backend/capsules-laboratoire-b38-api-demo-b2` (deux pushes autorisés par la session).
2. Ouvrir une PR en brouillon vers `main` (B38 n’empile aucune autre PR du domaine).
3. Faire relire par un relecteur humain différent de l’autrice (par ex. `ousseynoufayeisidk-sys`, désigné comme relecteur principal de la tâche 3). Discuter en revue les quatre points d’arbitrage listés dans `docs/quality/B38_B2_API.md` : nom de migration, câblage `demo`, stripage des middlewares, réutilisation des value objects B13.
4. Après revue, demander au responsable 1 d’ajouter la ligne manquante dans `docs/OPENAPI.yaml` (fragment déjà prêt) pour que `ApiInventoryTest` passe.
5. Observer la CI distante (`backend-ci` PHP 8.4 + 8.5) après le push.
6. Si demandé lors de la revue, ajouter un test de concurrence réelle `pcntl`/`symfony/process` sur la contrainte unique `demo_orders_idempotency_key_unique`, sur le modèle de B35.

## Fichiers à ne pas toucher jusqu’à nouvel ordre

- `backend/config/*`, `backend/bootstrap/app.php`, `backend/phpunit.xml`, `backend/.env.example`, `backend/composer.*`, `.github/workflows/*`.
- `backend/routes/api.php`, `backend/routes/api/identity.php`.
- `docs/OPENAPI.yaml` (fichier responsable 1 ; diff proposé déjà écrit dans la note de PR).

## Contrôles réellement exécutés

- `composer lint` PASS, `composer analyse` **[OK] No errors**.
- `composer test` : 267 tests / 2594 assertions, 1 échec attendu `ApiInventoryTest` (route B38 absente de `docs/OPENAPI.yaml` racine, fichier responsable 1).
- `composer test:integration` : **154 tests / 1332 assertions, OK** (dont 21 cas Demo B38 : 10 service + 5 http + 6 purge).
- `php scripts/generate-api-types.php` : 30 types, diff limité à B38.
- CI distante PR #28 (run 37497715618, avant les deux commits de purge) : `PHP 8.4 / PostgreSQL 17` et `PHP 8.5 / PostgreSQL 17` échouent sur le seul `ApiInventoryTest` ; `backend-ci` échoue par dépendance. Rouge attendue tant que `docs/OPENAPI.yaml` n'est pas complété.

## 2026-10-08 — B22 : reprise de main b76612d par merge local 285f91c. Suite Integration (hors Demo) : OK 397 tests / 3380 assertions. Pint, PHPStan 8 et composer test (398 / 3885) verts. Prerequis local a prevoir : role haas_demo_test + base haas_demo_bootstrap_test + DEMO_DB_PASSWORD pour Integration/Demo (non lancees localement, couvertes par la CI distante).

## 2026-10-08 - B23 : reprise de b22 par merge local 53ed6d1. Suite Integration (hors Demo) : OK 476 tests / 3724 assertions. Pint, PHPStan 8 et composer test (406 / 4010) verts.

## 2026-10-08 - B24 : reprise de B23 par merge local 1ce4f1a. Suite Integration (hors Demo) : OK 526 tests / 4063 assertions. Pint, PHPStan 8 et composer test (413 / 4090) verts.

## 2026-10-08 - B25 : reprise de B24 par merge local 83bd71a. Suite Integration (hors Demo) : OK 549 tests / 4127 assertions. Pint, PHPStan 8 et composer test (413 / 4129) verts.

## Reprise B25 — 11 octobre 2026

Worktree isolé `pr-ready-b25-20261011`, base PR34 `ce75045`. Snapshot de la révision approuvée corrigé après probe HTTP rouge ; suite HTTP 23 / 59 et Pint ciblé verts. Prochaines corrections : notification Q12 de publication et downgrade conservant empreintes/attributions. L’intégrateur publiera seulement après réception du code figé et CI exacte ; revue humaine en attente. Les anciens worktrees restent historiques.

Downgrade B25 corrigé sous verrou transactionnel : refus explicite des empreintes/approbations connues, garde inchangée ; legacy NULL down/up réel conservant dates, contenus et notes. Suite ciblée schéma + cinq courses réelle verte 10 / 79. Continuer Q12 dans ce worktree ; aucun test global concurrent ni push.

### Transfert B25 — 11 octobre 2026

Worktree `C:/laragon/www/HAAS_Projet/.worktrees/pr-ready-b25-20261011`, branche `review/pr-b25-20261011`. Microcommits précédents : snapshot `18bfbe0a460c4e82608dfdc8c02c84f6f35f9551`, downgrade `eceb4bcb2f5387891c98de00ce641894d27ed6c4`. Le SHA du commit Q12 est transmis dans le bilan réel, jamais inscrit dans lui-même.

Q12 prête à réception : événement UUID de revue, owner sous verrou, cible propriétaire UX08 future `/capsules/<UUID>/versions/<UUID>/edition`, aucune note/corps projeté, état publié visible et compte courant avant lecture/compteur/marquage ; retrait exclu. Migrateur conserve les types SQL installés et refuse de perdre les événements stables. Ciblés SQL57/467, APIInventory1/81, Pint complet/PHPStan8, 61 types API, docs18/18 et7/7 réussis. Code figé, aucun test B25 actif ; env/vendor ignorés conservés, runner racine obligatoire pour PHP. Root fusionne B24 documentaire `2463e4b`, vérifie manifest/backend, puis Unit+Integration complètes sur les bases B25 dédiées et CI exacte. Aucun push/ready/merge de PR/revue humaine par cet agent ; parent en attente de revue humaine.

## 2026-10-14 — Correctif rollback B33

Test `CapsulesMigrationTest::test_down_rollback_removes_all_capsule_schema_tables` étendu aux tables du laboratoire. Avant la boucle de rollback, le test dépose `lab_results`, `lab_runs` et `lab_definitions` dans l'ordre des FK (restrictOnDelete depuis ces tables vers `capsule_versions`). Rollback seul OK (1,27), Capsules OK (234,1178), Lab OK (81,455), lint passed, analyse [OK]. Tête distante `backend/capsules-laboratoire-b33-release` à 6951bb7 au départ ; PR #42.


## 2026-10-15 — B34 : lancement et quotas (préparé, PR en brouillon)

Branche : `backend/capsules-laboratoire-b34-lancement-lab`, base `backend/capsules-laboratoire-b33-release` (b089da4). PR 42 (B33) verte au départ. B34 ne touche pas B33 ; mêmes règles. Status visé en fin de session : **PR en brouillon**, pas DONE.

### Routes livrées

- `POST /api/v1/lab-runs` (`lab-runs.store`) : header `Idempotency-Key` (UUID v4) obligatoire ; body `{capsule_version_id, scenario}` strictement ; **202** avec `data.{id,state:"queued",scenario,version_id,created_at,follow_url}` et header `Location`. Rejet silencieux impossible : tout champ supplémentaire (code/command/url/runner_key/…) → 422.
- `GET /api/v1/lab-runs/{id}` (`lab-runs.show`) : propriétaire seul (404 pour les autres), aucun paramètre accepté. Pas de digest/manifeste/`definition_id`/`request_key` projetés.

Middlewares : `auth:sanctum, verified, throttle:30,1` sur le POST ; `throttle:60,1` sur le GET. Anonyme → 401 ; non vérifié ou suspendu → 403.

### Service livré

- `App\Services\Lab\StartLabRunService::handle(User, StartLabRunData, string)` via `IdempotencyService::execute()` + `pg_advisory_xact_lock(554478)` dans la transaction, puis contrôles de quota horaire glissant / capacité globale / borne de queue, puis insertion `lab_runs` queued aligné sur `ApprovedLabRegistry::manifest()`. Aucun worker démarré (B36). Aucune écriture dans `lab_results`.

### Fichiers hors domaine modifiés (consigner, pas prétendre)

- `backend/app/Support/Http/ApiExceptionRenderer.php` : ajout des deux exceptions `LabQuotaExceeded`/`LabCapacityReached` → 429 + `Retry-After`. Discutable en revue : préfère-t-on exposer un header `X-RateLimit-Remaining` ? Non livré ici (pas demandé par le cahier).
- `backend/routes/api/capsules-lab.php` : ajout des deux routes `lab-runs.*` ; `routes/api.php` intact.
- `docs/OPENAPI.yaml` : deux lignes `$ref`. `docs/api/openapi/lab.yaml` : fragment B34 (POST/GET + schémas `StartLabRunBody`, `QueuedLabRun`). `docs/api/generated/haas-api.d.ts` régénéré (66 types).
- `backend/tests/Integration/Capsules/CapsulesMigrationTest.php` : **non modifié par B34** (étendu plus tôt par le correctif rollback B33 du 2026-10-14). Les tables de B34 sont inchangées (`lab_runs` existe déjà), aucun ajout nécessaire à `DomainTables::dropAll()`.

### Prochaines étapes pour la propriétaire

1. Vérifier `git status -sb` propre et `git grep -n "CLAUDE" -- . ':!docs/execution/participants'` vide.
2. Régénérer `SHA256SUMS` par le script temporaire décrit en HANDOFF.md:45 (hors dépôt, `git ls-files --cached -z` sans SHA256SUMS, SHA-256 des octets de l'index, ordre existant conservé, absents ajoutés à la fin par ordre alphabétique, format `<hex>␣␣<chemin>`, LF). Commit dédié : `chore(integration): régénérer les empreintes de B34`.
3. `git push -u origin backend/capsules-laboratoire-b34-lancement-lab` (un seul push autorisé).
4. `gh pr create --draft --base backend/capsules-laboratoire-b33-release --head backend/capsules-laboratoire-b34-lancement-lab`.
5. `gh pr checks --watch` : attendre les deux jobs PHP 8.4 et PHP 8.5 avant tout `ready`.
6. Revue humaine distincte de l'autrice (`ousseynoufayeisidk-sys` relecteur principal possible). Points d'arbitrage :
   - Choix du `max_running=1` global (lecture restrictive partagée entre runs simples et comparateur).
   - `queue_limit=20` par membre : défaut prudent, à préciser.
   - `UNITS_PER_SIMPLE_RUN=1` : BV206 pourrait demander 2 unités par comparaison.
   - Visibilité GET restreinte au propriétaire : §26:957 reste ambigu.
7. Après revue, demander au responsable 1 d'ajouter dans `config/lab.php` : `'max_running' => env('LAB_MAX_RUNNING', 1), 'hourly_units' => env('LAB_HOURLY_UNITS', 5), 'queue_limit' => env('LAB_QUEUE_LIMIT', 20),`. Tant que ces clés sont absentes, le service lit via `config('lab.<clé>', <défaut>)` sans erreur.

### Fichiers à ne pas toucher jusqu'à nouvel ordre

- `backend/config/*`, `backend/bootstrap/app.php`, `backend/phpunit.xml`, `backend/composer.*`, `.github/workflows/*`.
- `backend/routes/api.php` (point d'entrée du responsable 1).

### Contrôles exécutés localement

- `vendor/bin/pint --test` : `passed`.
- `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` : `[OK] No errors`.
- `vendor/bin/phpunit --testsuite Unit,Feature,Architecture` : **439 / 4263 OK**.
- `php scripts/generate-api-types.php` : 66 types, diff limité à B34.
- Integration locale (`tests/Integration/Lab`, `tests/Integration/Capsules`) : **non exécutée** — le mot de passe de `haas_test` est stocké dans `~/.haas-test.env`, dont la lecture est interdite par consigne B34. CI distante responsable.

### Preuves conservées

- Commits locaux sur la branche (SHA réels transmis dans le bilan de session, jamais inscrits dans le commit).

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

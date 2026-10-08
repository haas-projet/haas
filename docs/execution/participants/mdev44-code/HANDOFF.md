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

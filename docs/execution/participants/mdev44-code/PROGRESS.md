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

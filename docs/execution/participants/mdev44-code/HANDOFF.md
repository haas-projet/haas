# Reprise — capsules/laboratoire

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

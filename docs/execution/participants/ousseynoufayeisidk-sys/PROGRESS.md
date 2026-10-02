# Progression — socle/auth

## 2026-10-01 — B01

S01/S02 : inventaire local dans `docs/VERSIONS.md`, organisation confirmée à trois ; réserves externes dans `docs/BLOCKERS.md`. Ces lots restent en cours.

B01 : squelette Laravel/PostgreSQL, UUID, sonde minimale, routes séparées et garde-fou SQL implémentés. Preuves : [B01_BOOTSTRAP.md](../../../quality/B01_BOOTSTRAP.md), 12 tests / 22 assertions, migrations et HTTP réels. Statut IN_REVIEW, sans revue humaine simulée.

Prochaine action : revue de la PR B01, puis B02 (outils PHP), B03 (CI), B04 (HTTP), B05 (identité) et B06–B09 (authentification). Domaines des deux collègues préservés.

## 2026-10-01 — B01 intégré

Fusion de la PR #4 autorisée explicitement par l'utilisateur et effectuée par commit `462af72b992ed9bc5c77440ac04ec41c87bf2efd`. Aucune revue GitHub d'un collègue présumée. B01 DONE ; code fusionné identique au code testé. Mise à jour documentaire du démarrage parallèle et synchronisation des branches, sans modification applicative. Prochain lot personnel : B02.

## 2026-10-01 — B02 en revue

Qualité locale livrée depuis `1735a69` : Pint, Larastan/PHPStan niveau 8, scripts Composer et test d'architecture AST. Lint/analyse réussis, 23 tests / 33 assertions distincts avec intégration PostgreSQL réelle. Un témoin a fait échouer les trois contrôles attendus, puis a été retiré ; le serveur de test est arrêté. Preuve : [B02_QUALITY.md](../../../quality/B02_QUALITY.md). Aucun contrôle distant prétendu réussi. Prochaine action : revue/intégration de B02, puis B03.

## 2026-10-01 — Préparation B03

Continuation demandée pendant la revue B02 : branche dérivée `backend/socle-auth-ci` depuis `b665a78`, PR précédente préservée. Workflow PHP 8.4/8.5 et PostgreSQL validé localement par actionlint ; contrôles documentaires 18/18 et 7/7. B03 IN_PROGRESS tant que son exécution GitHub n'a pas été observée. Preuve : [B03_CI.md](../../../quality/B03_CI.md). Aucun merge ni revue humaine simulé.

## 2026-10-02 — B03 en revue, CI verte

PR #6 ouverte contre la branche B02, commit workflow `fa0ffd6`. Run GitHub 36944003232 réellement réussi : PHP 8.4.26, PHP 8.5.11 et backend-ci ; 23 tests / 33 assertions par version, SQL compris. B03 IN_REVIEW. Mettre à jour la base de #6 vers main après intégration de #5 et vérifier les contrôles du dernier SHA. Prochain lot : B04.

## 2026-10-02 — B04 préparé

Branche `backend/socle-auth-http`, parent B03 `a895269`. Rendu des erreurs, UUID de requête, pagination bornée et OpenAPI commun avec fragments des trois pilotes. 62 tests / 575 assertions locaux réussis, dont PostgreSQL ; lint/analyse/Composer/audit réussis. [Preuve B04](../../../quality/B04_HTTP.md). IN_PROGRESS jusqu'à observation de sa CI. PR à cibler sur `backend/socle-auth-ci` tant que #5/#6 attendent leur intégration. Prochain lot B05 ; aucun merge implicite.

## 2026-10-02 — B04 en revue

PR #7 ouverte, commit `1c4c343`. CI run 36946538852 réellement verte et logs lus : PHP 8.4.26 et 8.5.11, 62 tests / 575 assertions chacun, SQL inclus, lint/analyse/audit/documentation réussis. B04 IN_REVIEW. Intégrer les prérequis #5 puis #6 avant B04 ; prochain lot B05. Aucun avis humain présumé.

## 2026-10-02 — Fusions B02–B04, puis B05

Sur instruction explicite, PR #5/#6/#7 fusionnées après synchronisation et contrôles ; main 438ff5a, CI post-fusion 36950943010 réussie. B02–B04 DONE. Branche B05 backend/socle-auth-identity créée depuis ce main ; identité, profils et technologies partagés avec migration nouvelle et protections des champs serveur. 98 tests / 739 assertions locaux réussis ; lint/analyse/Composer/audit réussis, cluster SQL arrêté. B05 IN_PROGRESS en attendant sa CI. [Preuve](../../../quality/B05_IDENTITY.md). Prochain lot B06, inscription ; aucun avis de collègue inventé.

## 2026-10-02 — B05 en revue

PR #8 ouverte contre main, commit 5913f37. Run 36951975464 réussi et logs lus : PHP 8.4.26/8.5.11, 98 tests / 739 assertions chacun, SQL inclus, lint/analyse/audit/documentation verts. B05 IN_REVIEW, sans fusion ni revue humaine présumée. Prochain lot B06 ; preuves et contrôles du dernier SHA dans la PR et le bilan.

## 2026-10-02 — B06 préparé

Branche backend/socle-auth-registration dérivée de B05 4de376f ; PR #8 préservée. Inscription, profil et acceptation versionnée atomiques ; Argon2id et liste blanche stricte. 153 tests / 1218 assertions locaux réussis, dont concurrence de deux processus PostgreSQL et panne SQL réelle. Preuve docs/quality/B06_REGISTRATION.md. IN_PROGRESS en attente de CI ; aucune fusion implicite. Prochain lot B07, sessions et CSRF/CORS.

## 2026-10-02 — B06 en revue

PR #9, commit applicatif 4e40f1e ; run 36954111512 réussi sous PHP 8.4.26/8.5.11, chacun 153 tests / 1218 assertions avec PostgreSQL. B06 IN_REVIEW ; #8 précède #9, aucune fusion présumée. Prochain lot B07.

## 2026-10-02 — B05/B06 intégrés, B07 préparé

Fusion autorisée de #8/#9, main 075e6eb et CI 36954939123 verte. B05/B06 DONE ; preuve MERGE_B05_B06.md. B07 depuis ce main : sessions Sanctum, login/logout, CSRF, CORS, limites et tests de cookies. 182 tests / 1538 assertions locaux réussis, SQL compris ; qualité/Composer verts. IN_PROGRESS avant CI propre ; prochain B08. Aucun déploiement ou revue humaine simulée.

## 2026-10-02 — B07 en revue

PR #10, applicatif a8bfcaa et correction CI 645e74e ; run 36957084503 réussi sur PHP 8.4.26/8.5.11, chacun 182 tests / 1538 assertions avec PostgreSQL. B07 IN_REVIEW. Échec CI initial dû aux origines de test corrigé et documenté, aucun contrôle assoupli. Prochain lot B08 ; dernier SHA documentaire et ses contrôles dans la PR.

## 2026-10-02 — B08 préparé

Branche permanente backend/socle-auth depuis b9b38db pour ne pas multiplier les branches. PR #10 préservée. Courriels de vérification et reset, liens sûrs, réponses neutres, consommation unique, file SQL chiffrée et empreinte de session dès login. 212 tests / 1948 assertions locaux réussis, dont PostgreSQL réel, worker array et deux processus concurrents de reset. Lint/analyse/Composer/audit verts ; cluster arrêté. B08 IN_PROGRESS avant sa CI ; preuve B08_ACCOUNT_MAIL.md, prochain B09.

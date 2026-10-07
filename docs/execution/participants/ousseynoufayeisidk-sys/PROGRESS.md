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

## 2026-10-02 — B08 en revue

PR #11 dépendante de #10, applicatif 3a3d241 ; run 37015668695 réussi sur PHP 8.4.26/8.5.11, chacun 212 tests / 1948 assertions SQL comprises. B08 IN_REVIEW, aucun merge ou avis humain présumé. Prochain lot B09. Dernier SHA documentaire et CI dans la PR ; conserver backend/socle-auth après fusion.

## 2026-10-02 — B09 préparé

Continuation de la partie socle demandée, avec bilan Systalink à chaque livraison. Branche temporaire backend/socle-auth-permissions depuis c8c0a30 ; PR #10/#11 préservées. Compte courant privé, capacités et propriété sans bypass admin, suspension et information publique de recours. Contrat CURRENT_ACCOUNT.md et preuve B09_CURRENT_ACCOUNT.md.

244 tests / 2406 assertions locaux réussis : 177 / 1721 sans base et 67 / 685 sur PostgreSQL dédié. Lint/analyse/Composer/audit verts. Aucun package ni migration ajouté. B09 IN_PROGRESS avant sa CI propre ; Systalink « En cours », aucune nouvelle carte terminée avant fusion. Prochain lot B10. Les scénarios métier des collègues et AC03/AC09 complets restent à exécuter.

## 2026-10-02 — B09 en revue

PR #13 contre backend/socle-auth, dépendante de #11/#10. Commit applicatif cb8f7379cd48ca2938774fd7912efcb94ce6a6ad ; CI 37074478785 réussie, journaux lus : PHP 8.4.26/8.5.11 avec PostgreSQL 17, chacun 244 tests / 2406 assertions. Lint/analyse/Composer/audit/documentation et backend-ci verts. B09 IN_REVIEW ; dernier SHA documentaire et sa CI dans la PR. Aucun merge ni avis humain présumé. Prochain lot B10. Systalink : B09 « En cours — prêt pour revue », aucune nouvelle carte « Terminé ».

## 2026-10-02 — B10, profils et technologies préparés

Continuation demandée. Branche temporaire backend/socle-auth-profiles depuis B09 6985eee ; PR #10/#11/#13 et branches des collègues préservées. Profils public/propre, PATCH sous version et verrou, huit technologies maximum, référentiel paginé, initiales Unicode, données privées exclues et liens GitHub sûrs. Migration additive ; aucun package ajouté. Contrat PROFILES.md, preuve B10_PROFILES.md.

294 tests / 2808 assertions locaux réussis : 213 / 2008 sans base, 81 / 800 PostgreSQL. Concurrence réelle observée avec deux workers en attente SQL, un seul gagnant et aucun mélange. Lint/analyse/Composer/audit réussis. B10 reste IN_PROGRESS : contributions réelles non raccordées faute de sources métier livrées ; null explicite, aucun chiffre inventé. PR de première livraison à publier et CI à observer. Systalink : « En cours ». Prochain lot indépendant : B12.

## 2026-10-02 — B10, première livraison publiée et CI verte

PR #14 en brouillon contre backend/socle-auth-permissions, commit applicatif c5c1706be17b917835d3fdc794249b779ae78a70. Run 37076878442 réussi, journaux lus : PHP 8.4.26/8.5.11 et PostgreSQL 17, chacun 294 tests / 2808 assertions (213 / 2008 sans base et 81 / 800 SQL). Pint, PHPStan niveau 8, Composer, audit, documentation et backend-ci verts. Preuve B10_PROFILES.md ; dernier SHA documentaire et ses contrôles dans la PR et le bilan.

B10 reste IN_PROGRESS : première partie profils/technologies validée, contributions réelles encore dépendantes de B19/B25/BV209 et des règles de visibilité/retrait. Aucun merge ni revue humaine présumé. Systalink : B10 « En cours », aucune nouvelle carte « Terminé ». Ordre d'intégration #10 → #11 → #13 → #14 ; prochain lot indépendant B12, audit et révisions.

## 2026-10-02 — B12, audit et révisions préparés

Continuation demandée. Branche backend/socle-auth-audit depuis 3ab1c02 ; PR #10/#11/#13/#14 et domaines des collègues préservés. AuditWriter et migration content_revisions, métadonnées par liste blanche, acteur/date serveur, transaction obligatoire et version sous verrou. UpdateProfileService écrit réellement son audit ; panne d'audit et rollback métier annulent tout. Purge interne autorisée, sans copie du contenu ancien, répétable et atomique ; aucun endpoint d'historique ou de modération ajouté.

322 tests / 2912 assertions locaux réussis (227 / 2023 sans base, 95 / 889 SQL), dont deux processus concurrents observés bloqués avant deux éditions et deux révisions ordonnées. Lint, analyse niveau 8, validation/prérequis/audit Composer verts ; aucun package ajouté. Cluster PostgreSQL dédié 54691/haas_audit_test arrêté. Contrat AUDIT_ET_REVISIONS.md et preuve B12_AUDIT.md ; B12 IN_PROGRESS avant publication/CI, puis revue. Aucun AC25 global, B31 ou revue humaine déclaré validé. Prochain B13 ; B10 reste en cours pour ses contributions. Systalink : B12 En cours, aucune nouvelle carte terminée.

## 2026-10-02 — B12 en revue, CI verte

PR #15 contre backend/socle-auth-profiles, commit applicatif 2d5c361cd264b158bd4a2709de65e4b15092a57e. Run 37079906877 réussi, journaux lus : PHP 8.4.26/8.5.11 et PostgreSQL 17, chacun 322 tests / 2912 assertions (227 / 2023 sans base, 95 / 889 SQL). Lint, analyse, dépendances, audit, documentation et backend-ci verts. B12 IN_REVIEW ; dernier SHA documentaire et ses contrôles dans la PR et le bilan. Aucun merge ni avis humain présumé.

Systalink : B12 « En cours — prêt pour revue », aucune nouvelle carte « Terminé » avant fusion. B10 reste IN_PROGRESS pour ses contributions ; B31 et AC25 global non reçus. Intégrer les prérequis #10/#11/#13/#14 avant #15 et revérifier après reciblage. Prochain lot B13 — Idempotence des commandes.

## 2026-10-03 — B13, idempotence préparée

Continuation demandée. Branche backend/socle-auth-idempotency depuis 7233019, PR #10/#11/#13/#14/#15 et domaines des collègues préservés. IdempotencyService, table api_idempotency, UUID v4 hashé, charge HMAC canonique bornée, résultat limité aux références/version. Acteur/Policy recontrôlés avant lecture du résultat, transaction/contrainte unique, TTL 24 h sans prolongation. Profil raccordé avec header facultatif ; commandes des collègues non simulées. Purge bornée et déclaration scheduler, sans activation en production.

361 tests / 3088 assertions locaux réussis (249 / 2078 sans base, 112 / 1010 PostgreSQL), dont deux processus observés simultanément en attente de verrou pour mêmes charges/charges différentes ; une seule modification, audit et intention. Lint, analyse niveau 8, Composer validation/prérequis/audit verts, aucune dépendance ajoutée. Cluster dédié 54692/haas_idempotency_test arrêté. Contrat IDEMPOTENCY.md, preuve B13_IDEMPOTENCY.md, OpenAPI 0.9.0. B13 IN_PROGRESS avant sa CI ; publier contre la branche B12. Prochain lot B29 — Notifications internes, avec événements à raccorder aux domaines livrés. B10 reste en cours ; Systalink B13 En cours, aucune nouvelle carte terminée.

## 2026-10-03 — B13 en revue, CI verte

PR #16 contre backend/socle-auth-audit, commit applicatif 86cf6fbdff38696ddd8411e1ec34af1c31811493. Run 37091330505 réussi, journaux lus : PHP 8.4.26/8.5.11 et PostgreSQL 17, chacun 361 tests / 3088 assertions (249 / 2078 sans base, 112 / 1010 SQL). Lint, analyse niveau 8, dépendances, audit, documentation et backend-ci verts. B13 IN_REVIEW ; preuve B13_IDEMPOTENCY.md. Dernier SHA documentaire et ses contrôles dans la PR et le bilan ; aucun merge ni avis humain présumé.

Systalink : B13 « En cours — prêt pour revue », aucune carte « Terminé » avant fusion vérifiée. B10 reste IN_PROGRESS pour les contributions. Intégrer les prérequis #10/#11/#13/#14/#15 avant #16 et revérifier après reciblage. Prochain lot B29 — Notifications internes ; raccorder seulement les événements réellement livrés, en coordination avec leurs pilotes.

## 2026-10-03 — B32 administration développée

Continuation de toute la partie socle demandée. B32 sur branche séparée depuis B13 7c466c9 : commandes admin, verrou du dernier administrateur, motif chiffré, audit atomique, suppression et version des sessions. 367 tests / 3244 assertions, PHPStan niveau 8 et Pint réussis sur PHP 8.5.10/PostgreSQL 17 dédié. Contrat ACCOUNT_ADMINISTRATION.md et preuves B32_ADMINISTRATION.md. B32 IN_PROGRESS avant CI ; publication contre B13, sans fusion ni revue humaine présumée.

Poursuivre B29 puis B30/B31 avec les ressources réellement disponibles. B10 et la réception B39–B44 dépendent aussi des autres pilotes. Aucun secret Qodana configuré dans GitHub au constat du 3 octobre ; projet demandé à l'utilisateur, aucun jeton collecté dans le chat. Pas de GO_FRONTEND ni GO_PRODUCTION.

## 2026-10-03 — B32 en revue ; B29 première livraison

B32 : PR #17, commit fc2785f68ea05a43c5cb4cf334e3e83daf4e97a5, CI 37135203385 verte, journaux lus : chaque PHP 8.4/8.5 exécute 249 tests / 2149 assertions et 118 tests / 1095 assertions PostgreSQL (367 / 3244). B32 IN_REVIEW, revue/fusion attendues.

B29 : outbox transactionnelle, livraison séparée bornée/dédupliquée, boîte privée et lu/non lu. Typage/formatage réussis ; 249 tests / 2214 assertions de base et 4 tests / 47 assertions SQL ciblés. Preuve B29_NOTIFICATIONS.md, contrat INTERNAL_NOTIFICATIONS.md. B29 IN_PROGRESS : abonnements aux événements métier des collègues encore absents. Publier en brouillon contre B32 ; prochain B30/B31 sur les profils disponibles. Systalink B32 prêt pour revue, B29 en cours.

## 2026-10-03 — B30/B31, modération du profil

379 tests / 3643 assertions complets réussis ; Pint/PHPStan niveau 8 verts. Signalement privé, quotas atomiques, file filtrée, décisions motivées et profil masqué par API ; audit/purge/notification transactionnels. Trois courses PostgreSQL réelles. Contrat MODERATION.md, preuve B30_B31_MODERATION.md. B30/B31 IN_PROGRESS pour les adaptateurs métier absents ; publier la première livraison contre B29. Aucun lot des collègues reçu par hypothèse.

B29 PR #18 en brouillon, CI 37135770117 verte sur 29803cb : chaque PHP 8.4/8.5 exécute 249 tests / 2214 assertions et 122 tests / 1142 assertions PostgreSQL, soit 371 / 3356. Journaux lus ; B29 reste partiel. B32 PR #17 prête pour revue. Prochain travail : B39/B40/B41 sur le socle disponible, exercice local B42, réserves Qodana/BACKEND_GATE ; aucune fusion implicite.

## 2026-10-03 — Préparation de la réception du socle

Demande : continuer toute la partie attribuée. Branche backend/socle-auth-reception depuis 264695d. B30/B31 #19 : CI 37140211552 réellement verte, journaux lus, 379 tests / 3643 assertions par PHP 8.4/8.5 avec PostgreSQL 17. B32 #17 prêt pour revue ; B29 #18 et B30/B31 #19 restent des livraisons partielles en brouillon.

Ajouts : inventaire routes/OpenAPI, génération de 28 types JSON sans SPA, contrôle console SQL/files, script borné de sauvegarde chiffrée/restauration locale dédiée et runbook. Types compilés avec TypeScript 5.9.3 temporaire (Apache-2.0 vérifiée). Exercice réel de restauration, intégrité des comptes/profils/rapports/audit/notification et déchiffrement contrôlés ; refus archive altérée/clé erronée/base non vide. 251 tests / 2452 assertions sans base et 3 tests / 11 assertions SQL ciblés réussis. Voir SOCLE_RECEPTION_PARTIELLE.md pour les limites et la CI propre à cette branche après publication.

Consolidation : B39–B42 IN_PROGRESS pour le périmètre disponible ; B43/B44 BLOCKED. Aucun travail des collègues inventé, aucun merge ni revue humaine, aucun frontend/déploiement. Tableau SYSTALINK_TASKS.md prêt à recopier ; seules B01–B06 sont des cartes entières terminées à ce stade. Compléter les domaines puis les raccordements et la recette avant réception globale.

## 2026-10-03 — Réception partielle publiée et CI verte

PR #20 en brouillon contre backend/socle-auth-moderation, code 68b00f8d3fa865bc8995a6b43dda52b178d47ba0. Run 37141557467 réussi, journaux lus : PHP 8.4/8.5 avec PostgreSQL 17, chacun 384 tests / 3702 assertions (251/2452 sans base, 133/1250 SQL). Pint/PHPStan niveau 8/audit/documentation/types générés verts. Exercice local de restauration et refus documentés dans SOCLE_RECEPTION_PARTIELLE.md ; 463 empreintes contrôlées. Cluster temporaire 54693 arrêté, service existant inchangé.

B01–B06 restent les seules cartes entières terminées ; B07/B08/B09/B12/B13/B32 prêts pour revue. Les autres cartes de la partie socle gardent les réserves explicites du tableau SYSTALINK_TASKS.md. Reprendre après publication des domaines métier/Qodana et revue réelle ; aucun gate ni frontend autorisé. Intégrer dans l'ordre des dépendances, recibler les PR avant de supprimer les branches temporaires fusionnées ; conserver les trois branches permanentes et main.

## 2026-10-03 — Fusions autorisées et quatre branches conservées

Demande explicite « faite ca » : intégration des PR #10, #11, #13–#20, dans cet ordre, sans revue humaine de collègue inventée. Heads synchronisés avec main par merges normaux, arbres inchangés, CI du SHA exact et journaux vérifiés avant chaque fusion. Résultat main a051e819cb923bc8e2755941cd494ec6d3f5cd81 ; CI post-fusion 37146178657 verte, chaque PHP 8.4/8.5 avec PostgreSQL : 384 tests / 3702 assertions, Pint/PHPStan/audit/documentation réussis. Preuve et tous les SHA : docs/quality/MERGE_SOCLE.md.

Les neuf branches temporaires sont supprimées après vérification des 30 références de commits dans main et de l'absence de PR dépendante. Restent main et les trois branches permanentes ; backend/socle-auth synchronisée, branches des collègues et PR #12 conservées. Aucune réécriture, aucun contournement de protection, aucun déploiement.

B07/B08/B09/B12/B13/B32 DONE ; le tableau Systalink comporte maintenant 12 lots terminés, 8 partiels et 2 bloqués. Contributions et autres raccordements attendent les pilotes ; Qodana et réception complète restent ouverts. Aucun GO_FRONTEND. Reprendre sur backend/socle-auth ; ne pas recréer les anciennes branches pour consulter leurs commits, tous conservés dans main.

## 2026-10-07 — Synchronisation B11 avant fusion

Demande explicite : vérifier et fusionner le travail de Madina dans le dépôt distant. Le complément #22 approuvé par mdev44-code est fusionné dans la branche B11 (957029b), après la fusion des enums #12 dans main (33eafa0). Synchronisation locale de main : trois conflits documentaires résolus sans suppression des contributions B11 ; aucun changement des migrations initiales. Les huit fichiers applicatifs ajoutés par le merge sont exactement les enums et tests de #12.

Contrôles PHP 8.5.10 / PostgreSQL 17 local isolé, base haas_b11_review_test sur 127.0.0.1:55447 : 312 tests / 2538 assertions sans SQL ; 151 tests / 1316 assertions SQL ; validate-pack 18/18 ; check-deployment-docs 7/7 ; diff --check propre. Publication de la synchronisation pour obtenir la CI du nouveau SHA avant fusion #23. Revue automatisée ; seule l'approbation humaine existante de #22 est constatée, aucune signature humaine inventée. Aucun gate ni frontend.

## 2026-10-07 — Correction de l'horodatage B1 après revue

PR #27 inspectée sur 48fc475. Défaut reproduit sur PostgreSQL isolé : received_at à 12:00+02:00 était enregistré comme 12:00 UTC. Le test de régression échoue avant correction (une assertion) ; ProcessTestEventService normalise maintenant l'instant en UTC avant insertOrIgnore. Le test compare l'instant réellement stocké.

Contrôles PHP 8.5.10 / PostgreSQL 17, base haas_maadinaa_test sur 127.0.0.1:55447 : Pint passé ; PHPStan niveau 8 sans erreur ; 281 tests / 2507 assertions hors SQL et 158 tests / 1373 assertions SQL réussis, dont la course B1-05 et le nouveau cas non UTC ; git diff --check propre. Publier le correctif sans réécrire les commits de Madina, recibler #27 vers main puis récupérer le main consolidé et observer sa CI avant fusion. B35 reste partiel : base haas_lab séparée et module pédagogique défectueux non livrés ; aucun BACKEND_GATE.

## 2026-10-07 — B1 synchronisé avec B11 consolidé

Main 17c6daa (#23 et son complément #22) intégré dans la branche B35 corrigée cdbe96f. Cinq conflits purement documentaires résolus en conservant les sections B11, B35 et les deux interventions de l'intégrateur ; l'ancien HANDOFF B35 est gardé comme instantané daté. Aucun code métier ni migration réécrits dans cette synchronisation.

Suites locales PHP 8.5.10 / PostgreSQL 17, base haas_maadinaa_test dédiée : 312 tests / 2538 assertions hors SQL et 176 tests / 1439 assertions SQL réussis (488 / 3977 au total). Publier ce merge puis attendre la CI du SHA exact avant fusion #27. B2 #28 bloqué après deux tests HTTP ciblés en échec : Origin https://demo.example.com donne 403, Origin HAAS stateful émet deux cookies. #29/#30 restent en brouillon avec défauts statiques de provenance/validation documentés au bilan.

## 2026-10-07 — Revue et intégration du travail de Madina

Demande explicite de vérification puis fusion dans le dépôt distant. PR #12 fusionnée dans main (33eafa0), complément #22 dans B11 (957029b), #23 dans main (17c6daa), puis #27 corrigée et synchronisée dans main (f1f6238). Les commits et historiques des participants sont conservés. Aucune approbation humaine inventée ; seule celle réellement présente sur #22 est constatée. Rapport : docs/quality/MERGE_MADINA.md.

Défaut UTC B1 reproduit puis corrigé dans cdbe96f ; test de régression réellement rouge avant correction. Dernier head B1 108aa7d : 312 tests / 2538 assertions hors SQL et 176 / 1439 sur PostgreSQL local isolé, soit 488 / 3977. CI 37637092346 réussie, mêmes résultats sous PHP 8.4/8.5, Pint/PHPStan/audit verts. Pack 18/18, déploiement documentaire 7/7 et diff --check propres ; empreintes actualisées dans le bilan. Cluster PostgreSQL temporaire arrêté en fin de session.

B11 DONE (13 lots backend entiers terminés). B35 IN_PROGRESS : isolation/connexion haas_lab et tests du défaut pédagogique absents. B38 BLOCKED : deux probes HTTP en échec sur #28 (origine démonstration 403 ; origine HAAS deux cookies), patch de reproduction livré dans le rapport. B22/B23 IN_PROGRESS, #29/#30 brouillons conservés avec défauts statiques détaillés, sans nouveaux tests HTTP exécutés. Prochain lot : corriger les capsules et l'isolation B2 ; B14 peut reprendre depuis B11. BACKEND_GATE non reçu ; aucun frontend ni déploiement.

## 2026-10-07 — README du projet corrigés

Demande utilisateur : corriger le README du projet. README principal actualisé d'après les fusions réelles #12/#23/#27 et MERGE_MADINA : 13 lots backend entiers intégrés, B35 partiel, B38 bloqué et capsules en brouillon. Présentation communautaire, parcours consentis, installation PowerShell, contrôles disponibles, liens de reprise et hébergement cible clarifiés. README backend aligné : retrait des mentions périmées B07/B08 en revue et routes toutes vides.

Relecture croisée automatisée des commandes depuis composer.json, COMMANDS et VERSIONS ; liens locaux relus et git diff --check propre. Aucun test applicatif ni serveur exécuté pour cette modification documentaire ; les chiffres de CI cités sont les preuves datées du lot précédent. Empreintes actualisées. Correction préparée sur la branche de PR #31, travail initial B14 préservé ; la fusion de cette PR nécessite l'approbation humaine exigée par GitHub. SHA réel à consulter dans le bilan de session.

### Publication distante bloquée — 7 octobre 2026

Correction des README commitée localement dans 9605a4744d8462ac0abf325eb30a75d97e8bf601. Trois pushes refusés par GitHub avec Internal Server Error ; publication REST et GraphQL également en échec. Vérification finale : la PR #31 et sa branche distante restent sur 1642f4e63a3cb19a3c919d6ea85783cda675a2b8, sans la correction README. Aucun contrôle de protection contourné. Reprendre la publication depuis le worktree review-maadinaa lorsque le service GitHub accepte les écritures ; l'approbation humaine de #31 restera requise avant fusion. Le README du worktree initial B14 et son travail préexistant sont préservés.

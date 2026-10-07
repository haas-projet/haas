# Reprise — socle/auth

## Dernier état — 7 octobre 2026, 19:27 UTC

**10 branches distantes** : les trois permanentes et main à `f1f6238`, six temporaires portant les PR ouvertes #24/#28/#29/#30/#31/#33. Après les fusions externes #32/#26/#25 vers leurs bases, B14–B17 ont été consolidés dans #24 par `d9cba0b`, CI `37666083469` verte sous PHP 8.4/8.5 et backend-ci. Backend identique à B17 `8234470`, validé 671 / 6805. B15/B16/B17 supprimées après ascendance vers B14 publié et contrôle d'absence de PR dépendante ; code et branches locales préservés. B14–B17 restent hors main, #24/#31 requièrent une approbation humaine. README publiés dans #31, bilan [BRANCH_CLEANUP_20261007.md](../../../quality/BRANCH_CLEANUP_20261007.md). Prochain B18 après coordination ; aucun gate, frontend ni déploiement. Les états précédents ci-dessous sont historiques.

## État du 7 octobre 2026 après publication communautaire

Main `f1f6238` contient les fusions #12/#23/#27 ; **13 lots backend entiers intégrés**, B35 partiel. README principal et backend publiés dans #31, approbation humaine obligatoire avant fusion. Deux temporaires intégrés supprimés et trois branches permanentes synchronisées avec main ; le snapshot 13 → 11 devient **12 références** après création de B17 : quatre permanentes et huit temporaires conservées.

B14 `230b83b`, B15 `a3eb9b9`, B16 `d3d8407` synchronisés, CI exacte verte. B17 est publié dans #32 vers B16 : métier `238d5e9`, correctif de tests `8234470`, CI [37662853388](https://github.com/haas-projet/haas/actions/runs/37662853388) verte sous PHP 8.4/8.5, **671 tests / 6805 assertions par version**, Pint/PHPStan réussis. Le premier échec de CI est conservé dans le bilan ; aucun traitement de production assoupli. Lire [BRANCH_CLEANUP_20261007.md](../../../quality/BRANCH_CLEANUP_20261007.md), les PR et le suivi de Lamine. Prochain B18 après coordination des revues ; #24 et #31 attendent une approbation humaine. B23 modifié extérieurement à `93fafea`, préservé sans nouvelle validation. Aucun gate, frontend ou déploiement ; les entrées suivantes sont historiques.

B01–B09, B12–B13 et B32 DONE (12 lots) après intégration autorisée. B10/B29/B30/B31 et B39–B42 IN_PROGRESS ; B43/B44 BLOCKED. S01/S02 restent partiels. Les dix PR du socle sont fusionnées dans main (a051e81, CI 37146178657 verte). Neuf branches temporaires supprimées, quatre branches permanentes conservées. Branche de reprise : `backend/socle-auth`. BACKEND_GATE NON REÇU, aucun GO_FRONTEND/GO_PRODUCTION. Lire docs/quality/MERGE_SOCLE.md et SYSTALINK_TASKS.md. Les entrées suivantes sont historiques.

PostgreSQL temporaire 54693 arrêté à la fin de cette réception. Les prochains tests SQL exigent une nouvelle base locale/CI dédiée `haas_*_test`, rôle `haas_test`, et une connexion explicite. Ne pas réutiliser automatiquement le port de recette. Aucun secret applicatif enregistré.

B01 est intégré dans main par la PR #4, commit `462af72b992ed9bc5c77440ac04ec41c87bf2efd`, sur autorisation explicite de fusion de l'utilisateur. B02 est maintenant IN_REVIEW sur cette branche : Pint 1.32.1, Larastan 3.12.2, PHPStan 2.2.16 niveau 8, contrôle AST. Aucun avis de collègue ni CI inventé. Lire `docs/quality/B02_QUALITY.md` : 23 tests / 33 assertions distincts réussis, SQL compris, témoin retiré et serveur temporaire arrêté.

Reprendre B03 après revue/intégration de B02 et synchronisation avec main. Composer 2.10.3 a été vérifié en copie temporaire ; installation globale inchangée. PHP minimal 8.4 reste à exécuter nativement dans la CI. Retrouver le SHA et la PR dans le bilan de session. Inscription et connexion non implémentées ; les deux collègues poursuivent les parties indépendantes selon `docs/execution/BACKEND_A_TROIS.md`.

Continuation active : `backend/socle-auth-ci`, issue de B02, pour préparer B03 sans modifier la PR #5. Workflow localement valide ; CI distante à observer après publication. Lire `docs/quality/B03_CI.md`. B03 doit être proposé contre `backend/socle-auth` puis reciblé vers main après intégration de B02 ; ne pas fusionner les prérequis dans l'ordre inverse. Les limites PHP 8.4/CI ci-dessus restent valables jusqu'à observation d'un run réel.

Mise à jour du 2 octobre 2026 : CI réellement verte dans le run 36944003232 sur `fa0ffd6`, PHP 8.4.26 et 8.5.11 avec PostgreSQL ; 23 tests / 33 assertions par version. B03 IN_REVIEW. Le dernier commit et son run sont indiqués dans la PR et le bilan. Intégrer #5 avant de recibler #6 vers main. Prochain lot : B04. Qodana et hébergement restent non vérifiés ; les mentions précédentes décrivent les étapes historiques.

Reprise B04 : rendu d'erreur/corrélation, pagination et contrat OpenAPI prêts localement. Lire `docs/quality/B04_HTTP.md`. 62 tests / 575 assertions locaux réussis, serveur PostgreSQL de test arrêté. Ajouter la PR contre B03 et observer sa propre CI avant IN_REVIEW. Aucune fusion ni signature de collègue simulée. Prochain lot B05, puis authentification B06–B09 ; CORS reste à vérifier dans B07.

État courant : PR #7 ouverte, B04 IN_REVIEW. Run 36946538852 sur `1c4c343` réussi sous PHP 8.4.26/8.5.11 : 62 tests / 575 assertions par version, PostgreSQL compris. Le dernier SHA documentaire et son run sont dans la PR et le bilan. Prochain lot B05 sur branche distincte si nécessaire ; conserver l'ordre d'intégration #5 → #6 → #7.

Mise à jour après autorisation « fusionner et continuer » : les trois PR sont fusionnées, main et sa CI sont verts. B05 préparé depuis ce main : 98 tests / 739 assertions locaux, modèles et contraintes SQL vérifiés ; PostgreSQL temporaire arrêté. Ouvrir la PR B05 contre main et lire sa CI propre. Utiliser handle et les factories non vérifiées par défaut ; les parcours d'inscription commencent en B06. Preuves MERGE_B02_B04.md et B05_IDENTITY.md.

État actif : B05 IN_REVIEW, PR #8 ouverte ; run 36951975464 réussi sur 5913f37 (PHP 8.4.26/8.5.11, 98 tests / 739 assertions par version, SQL inclus). Lire les contrôles du dernier SHA documentaire dans la PR. B06 vient ensuite ; si #8 est encore ouverte, continuer sur une branche dérivée séparée sans modifier sa PR.

Reprise active B06 : backend/socle-auth-registration, parent B05 4de376f. Inscription prête localement : 153 tests / 1218 assertions ; cluster PostgreSQL dédié arrêté. Ouvrir la PR contre backend/socle-auth-identity, puis observer sa CI. Consulter REGISTRATION.md et B06_REGISTRATION.md. Conditions à publier/configurer avant ouverture réelle ; cookies SPA/CORS en B07, courriels en B08. Aucun merge de #8 ni GO_FRONTEND présumé.

Mise à jour active : B06 IN_REVIEW dans #9, run 36954111512 réussi sur 4e40f1e ; 153 tests / 1218 assertions pour chacune des versions PHP 8.4.26/8.5.11 avec PostgreSQL. Reprendre B07 après lecture des contrats auth/CORS, sur branche séparée si #8/#9 restent ouvertes. Dernier SHA documentaire et CI dans la PR.

Reprise active : B05/B06 DONE après fusion autorisée #8/#9. B07 sur backend/socle-auth-sessions depuis 075e6eb ; 182 tests / 1538 assertions locaux, serveur PostgreSQL temporaire arrêté. Lire SESSIONS.md et B07_SESSIONS.md, publier contre main et observer la CI. Prochain lot B08 ; /me et Policies en B09. Ni conditions réelles ni GO_FRONTEND présumés.

État actif : B07 IN_REVIEW dans #10, run 36957084503 réussi sur 645e74e ; 182 tests / 1538 assertions pour chacune des versions PHP 8.4.26/8.5.11 avec PostgreSQL. Reprendre B08, courriels et reset, sur une branche distincte. Lire les contrôles du dernier SHA documentaire dans la PR ; aucune fusion de #10 ni revue de collègue présumée.

Reprise B08 : branche permanente backend/socle-auth, base b9b38db. Courriels/reset prêts localement, 212 tests / 1948 assertions, SQL compris ; serveur temporaire arrêté. Publier la PR dépendante de #10 et observer la CI. Lire ACCOUNT_MAIL.md et B08_ACCOUNT_MAIL.md. Conserver les trois branches de départ ; supprimer uniquement les temporaires fusionnées et sans PR dépendante. Prochain lot B09 ; /me et Policies métier non livrés, SMTP réel non vérifié.

État actif : B08 IN_REVIEW dans #11 ; CI 37015668695 verte sur 3a3d241, PHP 8.4.26/8.5.11 et PostgreSQL, chacun 212 tests / 1948 assertions. Fusion #10 avant reciblage/intégration #11 ; ne pas supprimer la branche permanente. Prochain lot B09, /me et capacités. Lire le dernier SHA documentaire et ses contrôles dans la PR.

## Reprise active B09

B09 préparé depuis B08 c8c0a30 sur backend/socle-auth-permissions. Compte courant et autorisations : CURRENT_ACCOUNT.md, preuve B09_CURRENT_ACCOUNT.md. 244 tests / 2406 assertions locaux, SQL compris ; lint/analyse/Composer/audit verts. Publier la PR contre backend/socle-auth puis observer sa CI avant IN_REVIEW. Intégrer #10 puis #11 avant B09 et recibler vers main à chaque étape, sans réécriture. Conserver les trois branches permanentes ; supprimer la temporaire B09 uniquement après fusion vérifiée.

Prochain lot : B10, profils publics et édition. ACCOUNT_SUPPORT_EMAIL est une adresse publique de recours à configurer avant ouverture réelle ; aucune adresse inventée. Pas de contact effectif, SMTP réel, frontend ou déploiement validé. B32 livrera la révocation globale des sessions. À chaque bilan demandé par l'utilisateur : indiquer la carte Systalink et distinguer « En cours — prêt pour revue » de « Terminé — fusion vérifiée ».

État actif B09 : IN_REVIEW, PR #13, applicatif cb8f737 ; run 37074478785 vert sous PHP 8.4.26/8.5.11 avec PostgreSQL, chacun 244 tests / 2406 assertions. Voir B09_CURRENT_ACCOUNT.md. Serveur PostgreSQL temporaire arrêté. Ordre d'intégration #10 → #11 → #13, avec reciblage et vérification des checks du dernier SHA. Systalink : B07/B08/B09 restent « En cours » avant fusion ; prochain lot B10.

## Reprise active B10

Base 6985eee, branche backend/socle-auth-profiles. Lire PROFILES.md et B10_PROFILES.md : profils/édition/technologies prêts localement, 294 tests / 2808 assertions ; version optimiste et deux vrais processus SQL, contrôles PHP verts. Publier une PR de première livraison contre backend/socle-auth-permissions puis observer sa CI. Intégrer #10/#11/#13 avant B10, sans réécriture et sans suppression des branches permanentes.

B10 reste IN_PROGRESS tant que les contributions réelles ne sont pas raccordées : null ne signifie pas zéro. Sources à coordonner avec LamineGL (résolutions), mdev44-code (publications/attributions), puis visibility/retraits/démonstration à tester ; voir BLOCKERS.md. B12, audit/révisions, est la prochaine partie indépendante. Carte Systalink B10 « En cours », aucune nouvelle carte terminée à cette étape.

## B10 publié — reprise après CI verte

PR #14 en brouillon, applicatif c5c1706 ; run 37076878442 réussi, PHP 8.4.26/8.5.11 avec PostgreSQL 17, chacun 294 tests / 2808 assertions. Lire PROFILES.md et B10_PROFILES.md ; consulter les contrôles du dernier SHA documentaire dans la PR. Code local propre et serveur de test arrêté.

B10 reste IN_PROGRESS : contributions réelles à raccorder avec les domaines concernés, aucun compteur fictif. La carte Systalink reste « En cours », même après une éventuelle fusion de cette première partie. Intégrer #10/#11/#13 avant #14, avec reciblage et vérification des contrôles ; préserver les trois branches permanentes. Prochain lot indépendant B12 (audit et révisions), B11 appartient à LamineGL. Aucun GO_FRONTEND ou déploiement.

## Reprise active B12 — audit et révisions

Branche backend/socle-auth-audit depuis 3ab1c02, B12 préparé et testé localement. Lire architecture/AUDIT_ET_REVISIONS.md et quality/B12_AUDIT.md depuis docs : journal privé, métadonnées limitées, droits actuels, purges sans copie ; l'échec d'audit annule le profil et les pivots. Premier adaptateur profil, domaines des collègues préservés. 322 tests / 2912 assertions, contrôles PHP verts ; PostgreSQL temporaire arrêté.

Publier la PR contre backend/socle-auth-profiles et observer sa CI avant IN_REVIEW. Intégrer #10 → #11 → #13 → première livraison #14 avant B12, avec reciblage et revérification ; aucune fusion implicite. B10 reste IN_PROGRESS pour les contributions. B31 complétera le retrait métier et ses autres projections, AC25 global reste à recevoir. Prochain lot B13 (idempotence). Systalink B12 reste En cours jusqu'à fusion ; conserver les trois branches permanentes.

## B12 en revue — reprise après CI verte

PR #15 ouverte contre backend/socle-auth-profiles, applicatif 2d5c361 ; run 37079906877 réussi, PHP 8.4.26/8.5.11 et PostgreSQL 17, chacun 322 tests / 2912 assertions. B12 IN_REVIEW. Lire le contrat AUDIT_ET_REVISIONS.md, la preuve B12_AUDIT.md et les contrôles du dernier SHA documentaire dans la PR. Aucun test SQL local à relancer sans une cible dédiée explicitement configurée ; cluster B12 arrêté.

Prochain lot B13 (idempotence des commandes), sur branche séparée si la revue attend. Intégrer #10/#11/#13 puis la première partie #14 avant #15 ; aucune fusion implicite et aucun nettoyage de branche non fusionnée. B10 reste en cours, B31/AC25 global attendent leurs parcours. Systalink : B12 « En cours — prêt pour revue », pas « Terminé » avant intégration vérifiée.

## Reprise active B13 — idempotence

Branche backend/socle-auth-idempotency depuis 7233019, PR B12 #15 encore ouverte. Lire api/IDEMPOTENCY.md et quality/B13_IDEMPOTENCY.md depuis docs. Déduplication réelle du profil, résultat minimal, droits courants, rollback commun métier/audit/intention, expiration 24 h et purge bornée. 361 tests / 3088 assertions locaux ; contrôles PHP verts ; PostgreSQL temporaire arrêté. La planification doit être activée et supervisée lors de l'exploitation, aucune tâche de production créée.

Publier la PR contre backend/socle-auth-audit et observer sa CI avant IN_REVIEW. Intégrer #10/#11/#13 puis première partie #14 et #15 avant B13, avec reciblage/retest ; aucun merge implicite, préserver les trois branches permanentes. Les futurs consommateurs doivent vérifier leur projection courante et respecter l'ordre de verrous ; ne pas recopier de corps privé. B10 reste en cours. Prochain lot B29 — Notifications internes, à découper selon les événements déjà livrés par les pilotes. Carte Systalink B13 En cours jusqu'à fusion vérifiée.

## B13 en revue — reprise après CI verte

PR #16 ouverte contre backend/socle-auth-audit, applicatif 86cf6fb ; run 37091330505 réussi, PHP 8.4.26/8.5.11 et PostgreSQL 17, chacun 361 tests / 3088 assertions. B13 IN_REVIEW. Lire le contrat api/IDEMPOTENCY.md, la preuve quality/B13_IDEMPOTENCY.md et les contrôles du dernier SHA documentaire dans la PR. PostgreSQL temporaire arrêté ; ne lancer de nouveaux tests SQL qu'avec une cible dédiée explicite.

Prochain lot B29 — Notifications internes, avec événements métier réellement disponibles et droits actuels. Intégrer #10/#11/#13 puis la première partie #14 et #15 avant #16 ; reciblage et revérification obligatoires. Aucun merge implicite ni suppression de branche non fusionnée. B10 reste en cours pour les contributions. Systalink : B13 « En cours — prêt pour revue », pas « Terminé » avant intégration vérifiée.

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

## 2026-10-07 — Reprise après revue de Madina

Fusions distantes vérifiées : #12 33eafa0, #22 957029b dans B11, #23 17c6daa et #27 f1f6238 dans main. Lire docs/quality/MERGE_MADINA.md pour les SHA complets, contrôles et limites. B11 DONE ; 13 lots backend entiers terminés. Correctif UTC B1 cdbe96f inclus. Head combiné 108aa7d : 488 tests / 3977 assertions locaux et sous les deux PHP de la CI 37637092346, contrôles verts.

Reprendre B22 #29 (retrait avec date de publication conservée, champs serveur, versions/technologies), puis B23 #30 (source vraiment résolue, validations, verrouillage/rejeu et tests HTTP). Les PR restent en brouillon. B38 #28 reste BLOCKED : probes Origin démonstration=403 et Origin HAAS=deux cookies ; appliquer le patch inerte docs/quality/probes/B38_ISOLATION.patch sur la branche B2 pour reproduire, avec base locale dédiée. Corriger origine/routage sans session et isolation réelle base/runtime/configuration, puis revalider la concurrence.

B35 IN_PROGRESS : service transactionnel et concurrence reçus, câblage haas_lab/isolation et module défectueux pédagogique à compléter avant B36. Le cluster PostgreSQL temporaire de revue est arrêté. Travail local initial B14 préservé ; ne pas écraser sa modification de routes. Toutes les branches distantes sont conservées. Aucun BACKEND_GATE, GO_FRONTEND ou GO_PRODUCTION.

## 2026-10-07 — Reprise après correction des README

README.md et backend/README.md présentent désormais les livraisons réellement intégrées, les limites B1/B2/capsules et le démarrage local documenté. Commandes et prérequis relus dans les sources du dépôt ; git diff --check propre. Aucun nouveau test applicatif ni serveur lancé. Les résultats 488 tests / 3977 assertions renvoient à la CI datée du 7 octobre, pas à une exécution de cette correction.

La correction rejoint le bilan documentaire de PR #31 ; approbation humaine requise par la protection GitHub avant fusion dans main. Prochain travail : réception de cette documentation, puis corrections capsules/isolation B2 indiquées dans MERGE_MADINA. BACKEND_GATE reste non reçu. Travail local B14 conservé ; aucun frontend ni déploiement.

### Publication distante bloquée — 7 octobre 2026

Correction des README commitée localement dans 9605a4744d8462ac0abf325eb30a75d97e8bf601. Trois pushes refusés par GitHub avec Internal Server Error ; publication REST et GraphQL également en échec. Vérification finale : la PR #31 et sa branche distante restent sur 1642f4e63a3cb19a3c919d6ea85783cda675a2b8, sans la correction README. Aucun contrôle de protection contourné. Reprendre la publication depuis le worktree review-maadinaa lorsque le service GitHub accepte les écritures ; l'approbation humaine de #31 restera requise avant fusion. Le README du worktree initial B14 et son travail préexistant sont préservés.

## 2026-10-07 — Reprise après publication et nettoyage des branches

La publication des README et du suivi a repris avec succès : PR #31 sur le head distant a56fae1c90780dc3bff2c0b186e208782fd65073. Ne plus reprendre les tentatives de publication de l'instantané précédent. L'approbation humaine de cette PR reste requise avant fusion dans main.

Main et les trois branches permanentes distantes portent f1f6238. Les branches temporaires B11-schema et B35 ont été supprimées après preuve de fusion et absence de PR ouverte dépendante ; le total distant est passé de 13 à 11. Sept branches non fusionnées et leurs PR sont conservées, notamment la chaîne B14 → B15 → B16 et les brouillons B22 → B23. Lire docs/quality/BRANCH_CLEANUP_20261007.md pour les pointes complètes et les preuves.

Reprendre la partie communautaire de Lamine selon ses dépendances et préparer B17 séparément. Le retour à quatre branches exige l'intégration vérifiée des travaux restants ou une organisation décidée explicitement ; ne pas supprimer leurs références pour atteindre le nombre cible. B38 reste bloqué ; aucun nouveau test applicatif ni gate reçu par ce nettoyage. Travail préexistant du workspace initial préservé.

Compte rendu prêt pour un commit local après pack 18/18, documentation de déploiement 7/7, 28 types API à jour sous PHP 8.5.10, UTF-8 strict et git diff --check vérifiés. Manifeste de 517 fichiers actualisé dans l'ordre existant. L'intégrateur publiera le commit ; aucune publication de ce complément ni nouvelle revue humaine présumée.

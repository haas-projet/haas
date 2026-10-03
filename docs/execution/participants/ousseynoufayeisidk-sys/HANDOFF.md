# Reprise — socle/auth

B01–B06 DONE ; B07/B08/B09/B12/B13/B32 IN_REVIEW ; B10/B29/B30/B31 IN_PROGRESS (raccordements métier restants) ; B39–B42 IN_PROGRESS (preuves du socle seulement) ; B43/B44 BLOCKED (Qodana, domaines, réception et revue humaine). S01/S02 restent partiels. Branche active : `backend/socle-auth-reception`, PR #20 en brouillon, CI 37141557467 verte, dérivée de #19. Aucun BACKEND_GATE, GO_FRONTEND ou GO_PRODUCTION validé. Lire docs/quality/SOCLE_RECEPTION_PARTIELLE.md et SYSTALINK_TASKS.md. Les entrées suivantes conservent l’historique.

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

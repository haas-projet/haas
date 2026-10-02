# Reprise — socle/auth

Branche active : `backend/socle-auth` (B08), avancée par fast-forward depuis B07 `b9b38db`, PR #10 encore ouverte. Laravel 13.34.0, PHP local 8.5.10, PostgreSQL 17.0 ; résolution Composer sur PHP 8.4.0. Lire `docs/COMMANDS.md`, `docs/quality/B08_ACCOUNT_MAIL.md` et la dernière entrée ci-dessous ; les entrées précédentes sont historiques.

PostgreSQL temporaire arrêté. Les prochains tests SQL exigent une nouvelle base locale/CI dédiée `haas_*_test`, rôle `haas_test`, et une connexion explicite. Ne pas réutiliser automatiquement le port de recette. Aucun secret applicatif enregistré.

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

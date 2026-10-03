# Reprise de session

Inspecter le dépôt, préserver les fichiers/commits/saisies existants, lire ADR-006. Appliquer F18 : projets ouverts, offres consenties, décision propriétaire, fil et projection publique contrôlés. Nouvelle recette avant GO_FRONTEND ; ne pas prendre un ancien gate pour un accord sur ce périmètre. Systalink/Vercel inchangé.

État courant : B01–B06 DONE ; B07/B08/B09/B12/B13/B32 IN_REVIEW ; B10/B29/B30/B31 IN_PROGRESS (raccordements métier restants) ; B39–B42 IN_PROGRESS (preuves du socle seulement) ; B43/B44 BLOCKED (Qodana, domaines, réception et revue humaine). S01/S02 restent partiels. Branche active : `backend/socle-auth-reception`, PR #20 en brouillon, CI 37141557467 verte, dérivée de #19. Aucun BACKEND_GATE, GO_FRONTEND ou GO_PRODUCTION validé. Lire [la réception partielle](../quality/SOCLE_RECEPTION_PARTIELLE.md) et le tableau individuel SYSTALINK_TASKS.md. Les sections suivantes conservent l’historique ; elles ne remplacent pas cet état actif.

## Reprise après préparation GitHub — 2026-10-01

Le dossier reçu est uniquement documentaire, sans historique Git préexistant ni application. Premier import préparé sur `main` pour le dépôt privé `haas-projet/haas`, à la demande explicite de l'utilisateur. Retrouver la référence réelle du commit dans `git log` et dans le bilan de session.

Contrôles documentaires : pack 18/18, déploiement 7/7 après alignement du script sur ADR-006, contrastes 22/22. Les configurations locales, secrets et dépendances sont exclus par `.gitignore` ; les exemples publics restent versionnés. Les empreintes du pack sont actualisées pour les fichiers ajoutés ou modifiés.

Les 122 lots restent TODO. Commencer par S01 puis S02 avant les lots backend ; BH01–BH10 précèdent B39/B44. Aucun GO_FRONTEND ni GO_PRODUCTION donné. Les tests applicatifs et contrôles distants restent à réaliser lorsque l'application et la CI existent.

## Organisation active — trois développeurs

Lire [BACKEND_A_TROIS.md](BACKEND_A_TROIS.md) avant toute modification. Répartition et attributions confirmées par l'utilisateur : #1 `ousseynoufayeisidk-sys` / `backend/socle-auth`, #2 `LamineGL` / `backend/communaute-entraide`, #3 `mdev44-code` / `backend/capsules-laboratoire` sur GitHub `haas-projet/haas`.

Le responsable 1 commence S01/S02 puis B01–B05 par petites PR. Les responsables 2 et 3 préparent leurs contrats et cas de test puis récupèrent le socle intégré. B11 précède B22 lorsque celui-ci le référence ; audit, idempotence et identité précèdent leurs consommateurs. BV201 précède BC07, puis BH05 ; les autres dépendances BH restent obligatoires.

Chaque participant tient ses PROGRESS/HANDOFF dans `docs/execution/participants/<login>/`. Le responsable 1 consolide le suivi global, les empreintes et les fichiers communs. Un lot avec ses tests par PR, revue par une autre personne et synchronisation depuis `main` avant chaque fusion. Ne pas lancer trois squelettes Laravel ni développer tout le domaine avant une première PR.

Cette session organise le travail et crée les tâches/branches ; elle ne réalise aucun des 72 lots backend. Aucun merge de code, déploiement ou validation de gate n'est effectué. Consulter le bilan et `git log` pour le SHA réel de la planification et vérifier les branches distantes avant de reprendre.

## Base de dossiers commune — 2026-10-01

`backend/README.md` décrit maintenant les 26 dossiers préparés avec `.gitkeep`. Ce sont des emplacements partagés, sans Laravel installé ni commande Artisan/Composer applicative. `backend/AGENTS.md` est préservé. B01 et les autres lots ne sont pas déclarés terminés.

La demande porte sur une base identique pour les trois branches. Vérifier leurs références distantes dans le bilan de session, récupérer sa branche avec `git fetch origin` puis `git pull --ff-only` depuis un répertoire propre. Ne jamais écraser une branche si un collègue y a déjà ajouté du travail.

Prochain travail : inventaire S01/S02 et initialisation B01 par `ousseynoufayeisidk-sys`. Le PHP CLI observé est 8.3.12, alors que la cible documentaire est PHP 8.4 ; confirmer le runtime choisi et l'hébergement avant l'installation. Le dossier PHP 8.5.10 détecté dans Laragon n'a pas encore été vérifié. PostgreSQL client présent ne signifie pas base ou rôle applicatif validé.

## Reprise active après B01 — 2026-10-01

Le socle est maintenant installé sur `backend/socle-auth` : Laravel 13.34.0, PHP minimum 8.4, runtime local testé 8.5.10, PostgreSQL 17.0, UTC et UUID utilisateur. `composer.lock` et les exemples d'environnement sont suivis ; `vendor/`, caches et configurations privées sont ignorés. Aucun fichier `.env` applicatif n'a été créé. Suivre [COMMANDS.md](../COMMANDS.md) pour configurer son poste.

Les 12 tests / 22 assertions passent, dont les migrations/UUID/FK sur une base PostgreSQL temporaire dédiée. `/up` répond réellement 200 sans session ni accès SQL. Consulter [la preuve B01](../quality/B01_BOOTSTRAP.md) pour les commandes, résultats et limites. Les serveurs de vérification sont arrêtés ; aucune migration n'a ciblé le service existant sur 5432.

B01 reste IN_REVIEW : préparer la revue humaine de la PR avant sa fusion. Aucun merge vers `main` ni vers les branches des collègues n'est effectué. Après intégration, chacun récupère le socle commun ; le responsable 1 continue B02 (qualité), puis B03 (CI), B04 (HTTP), B05 (identité) et B06–B09 (authentification). Ne pas empiler le prochain lot sur une PR en attente de revue.

S01/S02 restent partiels : hébergement, SMTP, Qodana et CI non vérifiés. La version minimale PHP 8.4 reste à exécuter en B03 ; Composer 2.8.5 émet des dépréciations sous PHP 8.5. Aucun contrôle absent n'est déclaré réussi, aucun BACKEND_GATE ou GO_FRONTEND donné. Les sections précédentes relatent les étapes historiques, pas l'état courant.

## Reprise après fusion autorisée — 2026-10-01

PR #4 fusionnée sur demande explicite de l'utilisateur : `462af72b992ed9bc5c77440ac04ec41c87bf2efd`. Aucune revue d'un autre développeur enregistrée sur GitHub et aucune CI à cette étape. La fusion conserve exactement le contenu applicatif testé en `1952bff`. B01 est DONE ; les mentions IN_REVIEW ci-dessus décrivent l'étape précédente.

Les trois branches doivent récupérer ce socle et le suivi d'intégration par avancement simple, sans écraser un éventuel travail supplémentaire. La publication vérifie les références distantes ; consulter le bilan final pour son commit. Chaque développeur travaille dans son clone et sa branche, avec son environnement et sa base de test.

La demande de travail parallèle permet à LamineGL et mdev44-code de coder dès maintenant leurs parties indépendantes (enums propres au domaine, DTO, règles et tests unitaires), pendant que le responsable du socle livre B02–B05 puis l'authentification. Les migrations/services qui consomment des référentiels ou modèles manquants attendent leurs prérequis avant validation et fusion. Ne pas recréer ces modèles partagés dans chaque branche. Le détail et les commandes sont dans `BACKEND_A_TROIS.md`.

## Reprise après B02 — 2026-10-01

B02 est prêt pour revue sur `backend/socle-auth`. Lire [la preuve](../quality/B02_QUALITY.md) et [les commandes](../COMMANDS.md) : Pint, PHPStan/Larastan niveau 8 et architecture sont exécutables ; 23 tests / 33 assertions distincts réussis, dont PostgreSQL réel. Le témoin volontairement incorrect a été retiré et le serveur SQL temporaire arrêté. Aucun fichier `.env` privé livré.

Composer 2.10.3 a été utilisé en copie temporaire vérifiée ; l'ancien Composer global reste inchangé. Les collègues devront récupérer B02 après fusion et lancer composer install pour recevoir les outils verrouillés. Ne pas annoncer la CI avant B03 ; PHP 8.4 natif et Qodana restent non exécutés. Aucun lot d'authentification ni gate validé par cette étape.

Ne pas démarrer B03 sur cette branche tant que sa PR B02 attend la revue, conformément au plan à trois. Après intégration : synchroniser main, créer backend-ci avec PostgreSQL dédié et actions épinglées, puis observer son exécution réelle. Retrouver le SHA et l'URL de PR dans le bilan de session.

## Continuation B03 isolée — 2026-10-01

À la nouvelle demande de continuer, B03 est préparé sur `backend/socle-auth-ci`, dérivée de B02. Le plan précise cette possibilité sans ajouter de changements à la PR en attente. La PR B03 doit cibler temporairement `backend/socle-auth`, puis main après intégration de B02 ; pas de force-push ni fusion inversée des prérequis.

Workflow validé par actionlint, actions et image PostgreSQL épinglées, permissions minimales. La matrice vérifie PHP 8.4/8.5 et termine par le contrôle stable backend-ci. Exécution distante encore à observer ; consulter la preuve B03 et le bilan avant de considérer PHP 8.4 ou la CI validés. Qodana et déploiement restent hors de ce lot.

## Reprise après CI verte — 2026-10-02

Le run GitHub 36944003232 de la PR #6 est réellement réussi sur `fa0ffd6` : PHP 8.4.26 et 8.5.11, 23 tests / 33 assertions par version et PostgreSQL réel. Les logs d'analyse/audit/tests ont été lus. Voir [B03_CI.md](../quality/B03_CI.md). La preuve initiale reste liée à ce SHA ; le bilan et les contrôles de PR donnent le résultat du dernier commit documentaire.

Intégrer B02 (#5) avant B03 (#6), puis recibler #6 sur main et revérifier le dernier commit. Aucune fusion ni approbation humaine inventée. Si le gestionnaire d'identifiants Git Windows bloque, l'authentification GitHub CLI est disponible sans modifier la configuration globale. Prochain lot : B04, contrat HTTP/erreurs, puis B05 identité. Aucun GO_FRONTEND ou déploiement.

## Reprise active B04 — 2026-10-02

Branche `backend/socle-auth-http`, dérivée de B03 `a895269`. Erreurs HTTP, corrélation, pagination et OpenAPI commun livrés ; lire [B04_HTTP.md](../quality/B04_HTTP.md) et [le contrat partagé](../api/HTTP_CONTRACT.md). 62 tests / 575 assertions locaux réussis, PostgreSQL compris ; analyse/lint/audit réussis. Cluster de test arrêté. Symfony YAML ajouté aux outils dev : composer install requis après récupération.

Ouvrir la PR contre `backend/socle-auth-ci`, observer la CI PHP 8.4/8.5 du dernier commit et consigner son run avant de déclarer IN_REVIEW. Garder #5 puis #6 puis B04 dans cet ordre d'intégration, recibler après fusion du prérequis sans force-push. Aucune authentification ni CORS livré par B04 ; prochain lot B05. Les trois pilotes peuvent utiliser les fragments et composants après intégration du socle, selon leurs prérequis.

Mise à jour active : PR #7 ouverte, B04 IN_REVIEW après le run 36946538852 sur `1c4c343` ; PHP 8.4.26 et 8.5.11, chacun 62 tests / 575 assertions avec PostgreSQL. La preuve B04 lie ce run immuable. Le dernier complément documentaire et ses contrôles sont indiqués dans la PR et le bilan. Reprendre B05 sur une branche dérivée distincte si les PR précédentes attendent encore leur intégration.

## Reprise active après fusions — B05

#5/#6/#7 sont fusionnées ; main à 438ff5a et CI post-fusion verte. B05 part directement de ce main, branche backend/socle-auth-identity. Modèles d'identité et référentiel, migration et contrats prêts localement ; 98 tests / 739 assertions réussis, SQL compris, serveur de test arrêté. Lire IDENTITY_DATA.md : utiliser handle, plus name ; les factories sont non vérifiées par défaut. Aucun endpoint d'inscription livré.

Proposer B05 contre main, observer sa CI propre avant IN_REVIEW. Prochain lot B06 : inscription, conditions versionnées et refus HTTP des champs serveur. Le test d'inscription privilégiée de B05 constate encore l'absence de route ; il ne remplace pas les tests du futur FormRequest. Les collègues récupèrent origin/main puis l'intègrent dans leur branche sans force-push. Aucun gate ni déploiement.

Mise à jour active : B05 IN_REVIEW, PR #8 ouverte contre main. Run 36951975464 sur 5913f37 réussi sous PHP 8.4.26 et 8.5.11, chacun 98 tests / 739 assertions avec PostgreSQL. Lire la preuve B05, puis reprendre B06 sur une branche distincte si #8 reste ouverte ; aucune fusion de B05 présumée. Le dernier SHA documentaire et son run sont dans la PR et le bilan.

## Reprise B06 — inscription

Branche backend/socle-auth-registration depuis B05 4de376f. PR #8 encore ouverte ; proposer B06 contre backend/socle-auth-identity et ne pas modifier les branches des collègues. Contrat REGISTRATION.md, preuve B06_REGISTRATION.md. Total local 153 tests / 1218 assertions, SQL et deux processus concurrents compris ; cluster temporaire arrêté.

Observer la CI propre de B06 avant IN_REVIEW. Conditions réelles absentes : REGISTRATION_TERMS_VERSION vide entraîne 503 ; utiliser uniquement des versions fictives en test, aucune approbation juridique présumée. Argon2id obligatoire ; traiter explicitement les anciens hashes bcrypt avant connexion B07. B07 doit livrer cookies Sanctum/CORS/login/logout, B08 les courriels. Aucun GO_FRONTEND ou déploiement.

État actif : B06 IN_REVIEW, PR #9, commit applicatif 4e40f1e. Run 36954111512 vert sur PHP 8.4.26 et 8.5.11, chacun 153 tests / 1218 assertions, PostgreSQL compris. Intégrer #8 avant #9 ; dernier SHA documentaire et sa CI dans la PR. Prochaine continuation : B07 sur une nouvelle branche dérivée si les PR attendent leur intégration.

## Reprise active B07

#8/#9 sont fusionnées sur autorisation utilisateur ; main local 075e6eb, CI post-fusion verte. B07 part de ce commit sur backend/socle-auth-sessions. Lire SESSIONS.md et B07_SESSIONS.md. 182 tests / 1538 assertions locaux réussis, PostgreSQL compris ; cluster temporaire arrêté. Composer install requis pour Sanctum 4.3.3 ; aucun autre package mis à jour.

Publier la PR B07 contre main et observer sa CI avant IN_REVIEW. Cookies + Origin/Referer de confiance + X-XSRF-TOKEN pour les mutations ; /me reste B09. B08 vient ensuite : courriels et reset, notamment pour les anciens hashes bcrypt refusés par le login. Conditions d'inscription réelles toujours absentes. Aucun GO_FRONTEND ou déploiement.

État actif : B07 IN_REVIEW, PR #10. Applicatif a8bfcaa, correction CI 645e74e ; run 36957084503 réussi, PHP 8.4.26/8.5.11 avec PostgreSQL, chacun 182 tests / 1538 assertions. Lire la preuve B07_SESSIONS.md et les checks du dernier SHA documentaire dans la PR. Prochain lot B08 sur branche dédiée ; ne pas modifier les branches des collègues. Les origines de test sont maintenant explicitement alignées, serveur local dédié arrêté.

Reprise B08 : backend/socle-auth depuis b9b38db ; 212 tests / 1948 assertions locaux réussis, PostgreSQL temporaire arrêté. Lire ACCOUNT_MAIL.md et B08_ACCOUNT_MAIL.md. Ouvrir la PR vers backend/socle-auth-sessions tant que #10 reste ouverte, observer sa CI avant IN_REVIEW. File account-mail dédiée sur SQL ; worker requis pour l'envoi, array local, SMTP réel non testé. Ne pas supprimer la branche permanente ; après fusion #10, recibler B08 vers main puis nettoyer la branche temporaire. Prochain lot B09, /me et capacités ; aucun GO_FRONTEND.

État actif B08 : IN_REVIEW, #11, commit 3a3d241 ; CI 37015668695 réussie sous PHP 8.4.26/8.5.11, chacun 212 tests / 1948 assertions avec PostgreSQL. Consulter les checks du dernier SHA documentaire dans la PR. Reprendre B09 après lecture des capacités ; préserver #10/#11 et les branches permanentes. Conditions réelles et SMTP restent à fournir/configurer avant ouverture réelle.

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

# Reprise de session

Inspecter le dépôt, préserver les fichiers/commits/saisies existants, lire ADR-006. Appliquer F18 : projets ouverts, offres consenties, décision propriétaire, fil et projection publique contrôlés. Nouvelle recette avant GO_FRONTEND ; ne pas prendre un ancien gate pour un accord sur ce périmètre. Systalink/Vercel inchangé.

État courant : B01–B04 DONE, PR #5/#6/#7 fusionnées sur autorisation explicite ; B05 IN_PROGRESS sur `backend/socle-auth-identity`. Voir `quality/MERGE_B02_B04.md`, `quality/B05_IDENTITY.md` depuis `docs/` et la dernière section. S01/S02 restent IN_PROGRESS. BH01–BH10 précèdent B39 et le frontend attend GO_FRONTEND humain.

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

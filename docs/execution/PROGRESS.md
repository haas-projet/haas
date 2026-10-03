# Suivi du dépôt HAAS

**Statut actif :** B01–B06 DONE ; B07 IN_REVIEW sur `backend/socle-auth-sessions` (PR #10, CI verte) ; B08 IN_REVIEW sur la branche permanente `backend/socle-auth` (PR #11, CI verte) ; B09 IN_REVIEW (PR #13, CI verte) sur `backend/socle-auth-permissions` ; B10 IN_PROGRESS sur `backend/socle-auth-profiles` (PR #14 en brouillon, CI verte, contributions restantes) ; B12 IN_REVIEW sur `backend/socle-auth-audit` (PR #15, CI verte) ; B13 IN_PROGRESS sur `backend/socle-auth-idempotency` ; S01/S02 IN_PROGRESS. Les autres lots restent TODO. 122 lots proposés, 106 identifiants antérieurs conservés. Ajouts BH01–10/FH01–05/RH01, F18, AC69–90 et UX21–23. Ne pas écraser les statuts ou preuves existants.

À renseigner après commandes réelles : date / lot / responsable / fichiers / commandes / observé / commit ou raison de non-commit / blocage / prochaine action. Les contrôles documentaires de ce pack ne valident pas BACKEND_GATE, FRONTEND_GATE ou RELEASE_GATE.

## 2026-10-01 — Préparation du premier envoi GitHub

- Demande utilisateur : pousser le dossier vers `https://github.com/haas-projet/haas.git` ; intervention Codex, sans revue humaine simulée.
- État initial : aucun dépôt Git local ; dépôt distant privé, vide et accessible en écriture. Identité Git existante conservée.
- Fichiers : import du pack existant, ajout de `.gitignore` et `.gitattributes`, correction du statut attendu dans `scripts/check-deployment-docs.mjs` pour ADR-006, rapport associé et empreintes actualisés.
- Contrôles exécutés : `node scripts/validate-pack.mjs` (18/18), `node scripts/check-deployment-docs.mjs` (7/7 après correction de l'ancienne attente), `node scripts/check-design-contrast.mjs` (22/22).
- Intégrité initiale : les 109 empreintes de `SHA256SUMS` correspondent aux fichiers reçus. Recherche de formats usuels de secrets sans correspondance ; les configurations livrées sont des exemples sans identifiants secrets.
- Revue de l'import : `git diff --cached --check` signale dix fins de ligne Markdown à deux espaces et deux lignes vides finales déjà présentes dans le pack ; contenu source conservé. Aucun autre problème détecté par le même contrôle avec `core.whitespace=-blank-at-eol,-blank-at-eof`.
- Commit initial : référence réelle à communiquer dans le bilan après création ; branche `main` destinée au dépôt demandé.
- Limites : aucun test applicatif, CI, contrôle Qodana, rendu visuel ou déploiement exécuté pendant cette intervention. Aucun lot applicatif ni gate déclaré terminé.
- Prochaine action de développement : S01 (inventaire complet de l'environnement), puis S02 et backend selon les dépendances.

## 2026-10-01 — Répartition backend entre trois développeurs

- Demande utilisateur : trois tâches backend, une branche par personne et des fusions coordonnées. Attribution explicitement confirmée : `ousseynoufayeisidk-sys`, `LamineGL`, `mdev44-code`.
- Point de départ : `8e1c4b9` synchronisé avec `origin/main`, répertoire propre, aucun backend applicatif. Trois collaborateurs constatés sur GitHub ; aucune tâche ni branche de travail préexistante.
- Livrables : `BACKEND_A_TROIS.md` (responsabilités, 72 lots, frontières, prérequis, petites PR), modèle de PR, mentions d'équipe actualisées dans les consignes/plan/architecture. Sources et livrables historiques conservés.
- Tâches GitHub créées et attribuées : #1 socle/authentification/intégration, #2 communauté/entraide, #3 capsules/laboratoire. Branches prévues depuis le même commit de planification : `backend/socle-auth`, `backend/communaute-entraide`, `backend/capsules-laboratoire`.
- Contrôle de répartition exécuté : 22 + 27 + 23 = 72 lots backend, aucun doublon ni omission. Comparaison avec `HEAD:docs/execution/tasks.json` : les 122 identifiants, statuts, ordres et dépendances sont préservés.
- Vérifications exécutées : `node scripts/validate-pack.mjs` (18/18), `node scripts/check-deployment-docs.mjs` (7/7), `git diff --check` (sans erreur). Empreintes actualisées avant le commit. Aucun test applicatif, CI ou Qodana exécuté pour cette organisation.
- Limites : aucune promesse de fusion automatique, aucune protection de branche activée, aucun lot applicatif déclaré terminé. La revue humaine de chaque PR reste requise par le processus d'équipe.
- Prochain travail : S01/S02 puis B01–B05 par le responsable du socle ; les autres préparent leurs contrats et relisent, puis synchronisent leur branche avant les lots métier dépendants.

## 2026-10-01 — Arborescence backend commune

- Demande utilisateur : créer d'abord les dossiers de base afin que les trois personnes disposent de la même structure. Périmètre retenu : dossiers versionnés, sans installation Laravel ni fonctionnalité métier.
- Point de départ : `7805723`, répertoire propre ; `main` et les trois branches distantes portent ce même commit, aucune PR ouverte constatée avant intervention.
- Livrables : 26 dossiers repères dans `backend/` avec `.gitkeep`, et `backend/README.md` décrivant les couches, les responsabilités et l'initialisation future. `backend/AGENTS.md` conservé sans modification.
- Inventaire local partiel : PHP CLI 8.3.12 avec pdo_pgsql, Composer 2.8.5, Node 24.19.0, npm 11.2.0 via `npm.cmd`, client PostgreSQL 17.0 et Git 2.45.1.windows.1. Le répertoire Laragon contient aussi une version PHP 8.5.10 ; elle n'a pas été sélectionnée ni testée. Ces observations ne valident pas les versions de l'hébergement ni la connexion à une base.
- Contrôles exécutés : présence des 26 fichiers `.gitkeep` vides, empreinte Git de `backend/AGENTS.md` inchangée, `node scripts/validate-pack.mjs` (18/18), `node scripts/check-deployment-docs.mjs` (7/7), `git diff --check` sans erreur. Sélection explicite des nouveaux dossiers et actualisation des empreintes avant commit.
- Aucun test applicatif, CI, Qodana, installation de dépendance ou accès à une base exécuté. Tous les lots du plan, y compris B01, gardent leur statut antérieur.
- Prochaine étape : S01/S02 puis B01 sur `backend/socle-auth`. La publication de cette arborescence initiale commune ne remplace pas la revue humaine des futures PR applicatives.

## 2026-10-01 — B01, premier socle applicatif

- Demande : commencer la partie socle/authentification de `ousseynoufayeisidk-sys`. Point de départ : `8aed3a8b9ec6b62348b0ff1584f1b018800f3c56`, base commune propre ; travail limité à `backend/socle-auth`.
- Livraison : Laravel 13.34.0, dépendances verrouillées pour PHP 8.4 minimum, PostgreSQL explicite, UTC, utilisateur UUID, migrations techniques, sonde `/up`, fichiers de routes par domaine et garde-fou des tests SQL. Aucune fonctionnalité d'authentification annoncée comme terminée.
- Preuves : [B01_BOOTSTRAP.md](../quality/B01_BOOTSTRAP.md), inventaire des 101 dépendances et licences, [versions](../VERSIONS.md), [commandes](../COMMANDS.md). Suivi individuel dans `participants/ousseynoufayeisidk-sys/`.
- Vérifications applicatives réelles sous PHP 8.5.10 : 12 tests, 22 assertions ; migrations sur PostgreSQL 17.0 temporaire dédié ; UUID et FK validés ; réponse HTTP `/up` 200 ; 36 fichiers PHP sans erreur de syntaxe ; validation Composer stricte, prérequis et audit réussis. Serveurs temporaires arrêtés ; service PostgreSQL existant non modifié.
- Suivi documentaire adapté aux statuts réels et aux fichiers livrés par Git, en excluant les dépendances/configurations ignorées. `backend/AGENTS.md` et l'arborescence commune préservés.
- Contrôles documentaires : `node scripts/validate-pack.mjs` 18/18, `node scripts/check-deployment-docs.mjs` 7/7, `git diff --check` sans erreur. Empreintes actualisées pour les fichiers livrés avant commit.
- Limites : PHP 8.4 natif, Pint/PHPStan, CI distante, Qodana et services d'hébergement non vérifiés. Composer 2.8.5 émet des dépréciations sous PHP 8.5. S01/S02 restent partiels. Aucun gate ni revue humaine simulé.
- Commit réel à consulter dans le bilan et la PR. B01 attend une revue avant fusion ; aucun merge vers `main` ou les branches des collègues. Prochain lot : B02 après intégration, puis B03/B04/B05 et authentification B06–B09.

## 2026-10-01 — Fusion du socle et démarrage parallèle

- Autorisation : l'utilisateur demande explicitement de terminer et fusionner la PR #4 pour travailler en parallèle. État initial propre, commit testé inchangé, PR fusionnable, aucune revue GitHub ni CI distante présente. Aucun avis d'un collègue inventé.
- PR #4 fusionnée à 23:06:57 UTC par commit de merge `462af72b992ed9bc5c77440ac04ec41c87bf2efd`, conservant `1952bff5566fd8e9a46e8745041024a9bef2e679`. Le diff entre ces deux arbres est vide ; les preuves applicatives existantes portent sur le même code.
- Suivi : B01 DONE, preuve enrichie de la fusion, README et HANDOFF actualisés. Le plan à trois permet désormais le code indépendant des domaines dès B01 et maintient les prérequis avant fusion des parties dépendantes. Aucun lot B02–B05/B11/B22 déclaré réalisé par cette adaptation.
- Synchronisation prévue par avancement simple des trois branches vers le même socle et ce suivi. Vérifier les références distantes dans le bilan final ; aucune suppression, aucun force-push ni écrasement de contribution.
- Changements de cette étape uniquement documentaires ; tests applicatifs non relancés sans changement de code. `node scripts/validate-pack.mjs` : 18/18 ; `node scripts/check-deployment-docs.mjs` : 7/7 ; `git diff --check` sans erreur. Empreintes actualisées avant le commit de suivi.
- Prochain lot du responsable 1 : B02. LamineGL commence les éléments indépendants B11 ; mdev44-code ceux de B22. Les référentiels B05, l'authentification et la CI restent à livrer.

## 2026-10-01 — B02, qualité PHP locale

- Demande : continuer la partie socle/authentification attribuée à `ousseynoufayeisidk-sys`. Base propre `1735a69`, aucune PR de cette branche ouverte au démarrage ; contributions des autres domaines préservées.
- Livraison : Pint, PHPStan/Larastan niveau 8 ciblant PHP 8.4, scripts Composer qualité/tests, contrôle AST des dépendances HTTP de Data/Services et tests négatifs. Aucun ignore général ni baseline. Quatre ajouts dev MIT, aucune version existante mise à jour.
- Résultats réels : lint et analyse passent ; 22 tests / 28 assertions sans base et 1 test / 5 assertions sur PostgreSQL 17.0 dédié. Le test d'architecture seul passe et est déjà compté dans les 22. Total distinct : 23 tests / 33 assertions. Installation verrouillée, validation Composer, prérequis et audit réussis.
- Témoin invalide ajouté puis retiré : les trois commandes lint, analyse et test:architecture échouent bien avec code 1. Corrections de diagnostics réels : contrôle de type APP_URL, annotations de tests et imports ordonnés. Serveur SQL temporaire arrêté ; service existant inchangé.
- Preuves : [B02_QUALITY.md](../quality/B02_QUALITY.md), [B02_DEPENDENCIES.json](../quality/B02_DEPENDENCIES.json), COMMANDS/VERSIONS et suivi individuel actualisés. Composer 2.10.3 temporaire vérifié, installation globale inchangée.
- Contrôles documentaires : pack 18/18, déploiement 7/7 et `git diff --check` sans erreur. Empreintes actualisées avant commit.
- Limites : PHP 8.4 natif, CI et Qodana non exécutés. B02 IN_REVIEW ; aucun merge autorisé implicitement par cette continuation, aucun gate ni revue humaine simulé. Commit réel et PR à retrouver dans le bilan.
- Prochain lot : B03 après revue/intégration de B02, puis B04/B05 et authentification. B11/B22 et leurs prérequis restent sous la responsabilité des autres développeurs.

## 2026-10-01 — Préparation B03 sur branche dérivée

- Demande utilisateur : continuer. B02 est encore en revue dans la PR #5 ; création de `backend/socle-auth-ci` depuis `b665a78` pour préserver sa PR. La préparation B03 cible temporairement la branche B02 et attend ses prérequis avant intégration dans main.
- Workflow Backend CI : PHP 8.4/8.5, PostgreSQL 17 isolé, installation verrouillée, lint/analyse/tests/audit/documents et résultat global backend-ci. Actions épinglées par SHA, image par digest ; permissions en lecture, aucun secret de production et aucun filtre de chemins sur les PR.
- Vérifications locales : actionlint 1.7.12 sans diagnostic (ShellCheck/Pyflakes non exécutés), pack 18/18, déploiement 7/7, diff sans erreur. Code applicatif inchangé depuis B02 et ses tests réels ; aucune nouvelle preuve SQL locale inventée.
- GitHub Actions activé, runners hébergés utilisés ; aucune protection ou option de facturation modifiée. CI distante à observer après publication ; B03 reste IN_PROGRESS à ce stade. Preuves et références : [B03_CI.md](../quality/B03_CI.md).
- Prochaine action : ouvrir la PR B03 dépendante de B02, observer ses contrôles et corriger tout échec réel avant de la déclarer prête pour revue.

## 2026-10-02 — CI B03 réellement exécutée

- Commit du workflow : `fa0ffd6da6ab9b8ba702d71815833c2d3f1c47ce`. [PR #6](https://github.com/haas-projet/haas/pull/6) vers `backend/socle-auth`, dépendante de la PR #5, restée inchangée. Aucun merge ni protection modifiée.
- [Run 36944003232](https://github.com/haas-projet/haas/actions/runs/36944003232) terminé avec success : PHP 8.4.26, PHP 8.5.11 et contrôle global backend-ci. Logs lus : 23 tests / 33 assertions par version, PostgreSQL réel, lint/analyse/audit et documentation réussis.
- B03 passe IN_REVIEW avec preuve réelle. La réserve PHP 8.4 natif est levée pour le socle testé ; Qodana, hébergement et fonctionnalités futures restent non vérifiés. Les 122 lots et leurs dépendances sont préservés.
- Le complément documentaire relance la CI sur son propre commit ; consulter les contrôles du dernier SHA dans la PR et le bilan. Prochain lot métier : B04 (HTTP/erreurs), puis B05 ; intégrer B02 avant B03 et conserver la revue humaine.

## 2026-10-02 — B04, contrat HTTP commun

- Continuation demandée : branche `backend/socle-auth-http` issue de B03 `a895269`, sans modification des PR #5/#6. Renderer commun, request_id, pagination 20/max50, contrat OpenAPI et fragments par domaine livrés. Voir [B04_HTTP.md](../quality/B04_HTTP.md) et [HTTP_CONTRACT.md](../api/HTTP_CONTRACT.md).
- Contrôles locaux réussis sous PHP 8.5.10 : lint, analyse, validation Composer/prérequis/audit ; 60 tests / 459 assertions sans base, 2 tests / 116 assertions PostgreSQL réel. Total 62 tests / 575 assertions. Cluster temporaire arrêté, aucune migration sur la base applicative.
- Ajout dev symfony/yaml v8.0.15, licence MIT vérifiée, aucune mise à jour des dépendances existantes. AC03/AC05 restent partiels : contrats HTTP testés, règles métier et frontend à vérifier ultérieurement.
- B04 attend l'observation de sa propre CI avant IN_REVIEW. Intégration dans l'ordre B02/B03/B04, sans revue humaine simulée. Prochain lot : B05.

## 2026-10-02 — B04 en revue, CI verte

- PR #7 ouverte contre B03, commit `1c4c343d11d4bf86e65c928a92e82b29758a04de`. Run 36946538852 réellement réussi : PHP 8.4.26, PHP 8.5.11, PostgreSQL 17 et backend-ci. Logs lus ; 62 tests / 575 assertions par version, lint/analyse/audit et documentation réussis.
- B04 IN_REVIEW, preuve enrichie ; 230 empreintes vérifiées sans différence. Aucun merge ni avis humain. Le complément de preuve relance la CI ; consulter le dernier SHA/run de la PR et du bilan. Prochain lot B05.

## 2026-10-02 — Fusions autorisées et B05

- Demande explicite « fusionner et continuer ». #5, #6, #7 fusionnées dans cet ordre, sans force-push ni revue simulée ; #6/#7 reciblées vers main, synchronisées et retestées avant fusion. Main à `438ff5a866e8141fb55edfe1c09fc2869d95952b`, CI post-fusion verte (36950943010). Preuve et commits : [MERGE_B02_B04.md](../quality/MERGE_B02_B04.md). B02–B04 DONE.
- Nouvelle branche B05 issue de ce main. Modèles User/Profile/Technology, enums, defaults sûrs, champs privés, migration additive et précontrôle des identités existantes. Contrat : [IDENTITY_DATA.md](../architecture/IDENTITY_DATA.md).
- Contrôles locaux réussis : 73 tests / 517 assertions sans base, 25 tests / 222 assertions SQL, total 98 / 739 ; lint/analyse/Composer/audit réussis. Migration, contraintes et rollback exécutés uniquement sur PostgreSQL dédié puis serveur arrêté. Preuve : [B05_IDENTITY.md](../quality/B05_IDENTITY.md).
- B05 attend sa propre CI avant IN_REVIEW ; aucune inscription/connexion disponible ni AC04 intégralement reçu. Prochain lot B06. Les collègues intègrent origin/main dans leur propre branche ; leurs commits et branches sont préservés.

## 2026-10-02 — B05 en revue, CI verte

- PR #8 ouverte contre main, commit applicatif `5913f3753ba65c6a42c26a19066bf726da24464d`. Run 36951975464 réellement réussi, logs lus : PHP 8.4.26 et 8.5.11, 98 tests / 739 assertions par version avec PostgreSQL ; lint/analyse/audit/documentation réussis, backend-ci vert.
- B05 IN_REVIEW ; 243 empreintes locales vérifiées. Les PR #5/#6/#7 sont fusionnées ; #8 reste ouverte, aucun avis humain simulé. Preuve B05 enrichie ; le dernier commit documentaire et sa CI sont dans la PR et le bilan. Prochain lot B06.

## 2026-10-02 — B06, inscription

Continuation demandée ; branche distincte issue de B05 4de376f, PR #8 conservée ouverte. Inscription atomique membre/profil/conditions versionnées, validation stricte sans champ serveur, secret Argon2id entier, Resource privée et erreurs du contrat B04. Lire docs/api/REGISTRATION.md et docs/quality/B06_REGISTRATION.md.

Tests locaux réels : 118 tests / 922 assertions sans SQL, 35 tests / 296 assertions PostgreSQL ; total 153 / 1218. Deux processus concurrents donnent un seul compte complet ; panne SQL contrôlée annulée sans secrets dans le journal applicatif. Nouveau cluster temporaire dédié arrêté après usage. Aucun nouveau package.

B06 IN_PROGRESS en attente de sa propre CI. Conditions publiées/version réelle à fournir avant ouverture de l'inscription ; par défaut 503. Connexion, cookies SPA/CORS en B07 ; vérification et reset en B08. Aucune fusion, validation humaine ou gate présumée. Commit et run réels à consigner après création.

## 2026-10-02 — B06 en revue

Commit applicatif 4e40f1e7879a6594d2f2869e7fd84b8f9aea3a0c, PR #9 contre backend/socle-auth-identity. Run 36954111512 réussi et logs lus : PHP 8.4.26/8.5.11, chacun 153 tests / 1218 assertions avec PostgreSQL, lint/analyse/audit/documentation verts. B06 IN_REVIEW. Preuve B06_REGISTRATION.md ; aucun merge ou avis humain simulé. Prochain lot B07.

## 2026-10-02 — Fusions B05/B06 et préparation B07

Autorisation explicite : « fusionner et continuer ». #8 fusionnée (2a6b737), #9 reciblée/synchronisée, CI 36954760336 verte puis fusionnée (075e6eb). CI main 36954939123 réussie. B05/B06 DONE ; preuve MERGE_B05_B06.md, aucune revue de collègue simulée.

B07 sur backend/socle-auth-sessions : login/logout/csrf-cookie, sessions Sanctum, CORS exact, CSRF réellement actif, cookies/rotation/invalidation, limites de connexion, service de vérification et contrat OpenAPI. Sanctum 4.3.3 MIT seul package ajouté, 106 versions conservées. 182 tests / 1538 assertions locaux réussis, dont PostgreSQL et anciens scénarios concurrents ; analyse, lint, validation Composer, prérequis et audit réussis. Cluster temporaire arrêté.

Preuve B07_SESSIONS.md, contrat SESSIONS.md. B07 IN_PROGRESS jusqu'à observation de sa CI. Aucun /me, courriel de compte, navigateur sur domaines réels, Qodana ou déploiement validé. Prochain lot B08, puis B09. Conditions réelles toujours à fournir ; bcrypt historique exige la réinitialisation B08. Commit réel et CI à consigner après publication.

## 2026-10-02 — B07 en revue

PR #10 contre main ; applicatif a8bfcaa, correction des origines CI 645e74e. Premier run en échec conservé dans la preuve ; run 36957084503 réussi sur 645e74ea0dd868317c89782fee479fddb37d2d06, PHP 8.4.26/8.5.11, chacun 182 tests / 1538 assertions avec PostgreSQL. Lint/analyse/audit/documentation et backend-ci verts, journaux lus. B07 IN_REVIEW ; aucun avis humain ou merge présumé. Prochain lot B08, courriels de compte ; dernier SHA documentaire et CI dans la PR et le bilan.

## 2026-10-02 — B08 sur la branche permanente

Préférence confirmée : conserver main et les trois branches de départ. Les quatre anciennes branches temporaires fusionnées ont été retirées après vérification des pointes dans main ; sessions reste pour #10. Branche backend/socle-auth avancée par fast-forward depuis b9b38db, sans modifier #10 ni les branches des collègues.

B08 : vérification signée/expirante, renouvellement limité, reset neutre et à usage unique, file mail chiffrée et transactionnelle, vrai transport local array, révocation des sessions et protection contre une connexion tardive. Contrat ACCOUNT_MAIL.md, preuve B08_ACCOUNT_MAIL.md, aucun package ajouté. 212 tests / 1948 assertions locaux réussis, dont 54 tests SQL et deux processus concurrents de reset ; lint/analyse/Composer/audit verts. Cluster dédié arrêté. IN_PROGRESS avant CI propre, PR dépendante de #10 à publier. Prochain lot B09 ; SMTP réel, Qodana et domaines finaux non testés.

## 2026-10-02 — B08 en revue

PR #11 dépendante de #10, commit applicatif 3a3d24189a13fb5b6131b1e166791f1af21c13f1. Run 37015668695 réussi, logs lus : PHP 8.4.26/8.5.11, chacun 212 tests / 1948 assertions avec PostgreSQL, qualité/audit/documentation verts. B08 IN_REVIEW. Intégrer #10 avant #11, conserver backend/socle-auth. Prochain lot B09 ; dernier SHA documentaire et CI dans la PR. Aucune fusion, revue humaine ou délivrabilité SMTP inventée.

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

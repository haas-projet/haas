# Reprise de session

Inspecter le dépôt, préserver les fichiers/commits/saisies existants, lire ADR-006. Appliquer F18 : projets ouverts, offres consenties, décision propriétaire, fil et projection publique contrôlés. Nouvelle recette avant GO_FRONTEND ; ne pas prendre un ancien gate pour un accord sur ce périmètre. Systalink/Vercel inchangé.

État courant : B01 vérifié localement, en attente de revue sur `backend/socle-auth` ; voir la dernière section et `quality/B01_BOOTSTRAP.md` depuis `docs/`. S01/S02 restent IN_PROGRESS. Retrouver le SHA réel dans la PR et `git log`. Prochain lot : B02 après intégration de B01 ; BH01–BH10 précèdent B39 et le frontend attend GO_FRONTEND humain.

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

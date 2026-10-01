# Reprise de session

Inspecter le dépôt, préserver les fichiers/commits/saisies existants, lire ADR-006. Appliquer F18 : projets ouverts, offres consenties, décision propriétaire, fil et projection publique contrôlés. Nouvelle recette avant GO_FRONTEND ; ne pas prendre un ancien gate pour un accord sur ce périmètre. Systalink/Vercel inchangé.

Dernier lot réellement fini / SHA / commandes / blocages : à renseigner depuis le dépôt. Prochain lot : première dépendance manquante de BH01–BH10 avant B39, puis frontend uniquement après GO_FRONTEND humain. Ne pas déclarer les fichiers documentaires comme modules implémentés.

## Reprise après préparation GitHub — 2026-10-01

Le dossier reçu est uniquement documentaire, sans historique Git préexistant ni application. Premier import préparé sur `main` pour le dépôt privé `haas-projet/haas`, à la demande explicite de l'utilisateur. Retrouver la référence réelle du commit dans `git log` et dans le bilan de session.

Contrôles documentaires : pack 18/18, déploiement 7/7 après alignement du script sur ADR-006, contrastes 22/22. Les configurations locales, secrets et dépendances sont exclus par `.gitignore` ; les exemples publics restent versionnés. Les empreintes du pack sont actualisées pour les fichiers ajoutés ou modifiés.

Les 122 lots restent TODO. Commencer par S01 puis S02 avant les lots backend ; BH01–BH10 précèdent B39/B44. Aucun GO_FRONTEND ni GO_PRODUCTION donné. Les tests applicatifs et contrôles distants restent à réaliser lorsque l'application et la CI existent.

## Organisation active — trois développeurs

Lire [BACKEND_A_TROIS.md](BACKEND_A_TROIS.md) avant toute modification. Répartition et attributions confirmées par l'utilisateur : #1 `ousseynoufayeisidk-sys` / `backend/socle-auth`, #2 `LamineGL` / `backend/communaute-entraide`, #3 `mdev44-code` / `backend/capsules-laboratoire` sur GitHub `haas-projet/haas`.

Le responsable 1 commence S01/S02 puis B01–B05 par petites PR. Les responsables 2 et 3 préparent leurs contrats et cas de test puis récupèrent le socle intégré. B11 précède B22 lorsque celui-ci le référence ; audit, idempotence et identité précèdent leurs consommateurs. BV201 précède BC07, puis BH05 ; les autres dépendances BH restent obligatoires.

Chaque participant tient ses PROGRESS/HANDOFF dans `docs/execution/participants/<login>/`. Le responsable 1 consolide le suivi global, les empreintes et les fichiers communs. Un lot avec ses tests par PR, revue par une autre personne et synchronisation depuis `main` avant chaque fusion. Ne pas lancer trois squelettes Laravel ni développer tout le domaine avant une première PR.

Cette session organise le travail et crée les tâches/branches ; elle ne réalise aucun des 72 lots backend. Aucun merge de code, déploiement ou validation de gate n'est effectué. Consulter le bilan et `git log` pour le SHA réel de la planification et vérifier les branches distantes avant de reprendre.

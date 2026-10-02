# B03 — CI backend initiale

Date : 1er octobre 2026. Branche de préparation : `backend/socle-auth-ci`, issue de B02 `b665a78e769def9d980917627d12c344ab3233a8`. B02 reste proposé dans la PR #5 ; la PR B03 cible temporairement `backend/socle-auth` pour garder un diff limité à la CI. Aucune revue ni fusion de B02 présumée.

## Workflow

`.github/workflows/backend-ci.yml` s'exécute sur chaque PR, sur les pushes de main et à la demande après son intégration. Aucun filtre de chemins : une PR documentaire reçoit aussi un résultat. Matrice PHP 8.4 / 8.5 sur Ubuntu 24.04, Composer 2.10.3, Node 24.19.0 pour les scripts documentaires et PostgreSQL 17 avec une base neuve par job.

Chaque job installe depuis composer.lock, vérifie les prérequis et l'absence de modification du lockfile, puis lance lint, analyse, test, test:integration, audit et les deux contrôles documentaires. Le job final `backend-ci`, exécuté même après échec d'un prérequis, exige la réussite des deux versions PHP. Il fournit un nom stable utilisable par une future protection de branche ; aucune protection n'est modifiée ici.

Permissions contents:read uniquement, identifiants Git non persistés, aucun secret de production ni environnement de déploiement. PostgreSQL est accessible en loopback du runner ; rôle haas_test, base haas_ci_test et mot de passe de fixture public limité au conteneur jetable. Chaque test SQL conserve le garde-fou HAAS. Les anciennes exécutions de la même PR sont annulées lors d'une mise à jour ; limite de 15 minutes par job PHP.

## Références vérifiées

| Composant | Version / référence | Licence |
|---|---|---|
| actions/checkout | v7.0.1 / 3d3c42e5aac5ba805825da76410c181273ba90b1 | MIT |
| actions/setup-node | v7.0.0 / 820762786026740c76f36085b0efc47a31fe5020 | MIT |
| shivammathur/setup-php | 2.37.2 / f3e473d116dcccaddc5834248c87452386958240 | MIT |
| Image officielle postgres:17 | sha256:d74eeac9a635390a49bc21bd49fccd973de707e2a53a76ac49b552b8712ec46f | Notices de l'image officielle conservées, aucune image redistribuée par le dépôt |
| actionlint local | 1.7.12 ; archive Windows SHA-256 6e7241b51e6817ea6a047693d8e6fed13b31819c9a0dd6c5a726e1592d22f6e9 | MIT |

Tags résolus par l'API GitHub puis définitions action.yml inspectées ; digest PostgreSQL lu dans le registre officiel Docker. actionlint téléchargé dans un dossier temporaire, empreinte vérifiée contre le fichier de sommes de la release. Aucun binaire d'outil ajouté au dépôt.

Sources : [services PostgreSQL GitHub](https://docs.github.com/en/actions/tutorials/use-containerized-services/create-postgresql-service-containers), [syntaxe des workflows](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax), [setup-php](https://github.com/shivammathur/setup-php).

## Vérifications et limites

- actionlint 1.7.12 sur le workflow : réussi, sans diagnostic. ShellCheck/Pyflakes non disponibles dans cette exécution Windows et désactivés explicitement pour ce contrôle ; ils ne sont pas déclarés réussis.
- Commandes PHP locales déjà exécutées dans B02 sur ce même code applicatif : 23 tests / 33 assertions, lint/analyse et PostgreSQL dédiés réussis ; voir [B02_QUALITY.md](B02_QUALITY.md). Aucune modification applicative dans B03.
- GitHub Actions est activé sur ce dépôt privé ; aucun runner auto-hébergé configuré. Le workflow utilise des runners GitHub hébergés. Aucun abonnement, budget ou protection modifié.
- Exécution distante initiale : **à observer après publication**. Aucun résultat vert, PHP 8.4 natif ou exécution PostgreSQL distante annoncé à ce stade. Les références du run réel seront consignées après observation.
- Qodana, hébergement, authentification et BACKEND_GATE ne sont pas couverts par ce lot.

Après intégration de B02 dans main, recibler la PR B03 sur main, intégrer origin/main sans réécriture forcée et vérifier les contrôles du dernier commit avant fusion. La continuation demandée par l'utilisateur autorise cette préparation séparée, sans ajouter B03 à la PR B02.

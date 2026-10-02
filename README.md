# HAAS — Help as a Service

**Rencontrez-vous en construisant quelque chose ensemble.** Communauté, projets, échanges et solutions réutilisables. L’atelier permet de documenter certaines améliorations ; une question simple ne requiert ni code ni laboratoire.

## Mise à jour du dossier
F18 « Coup de main » : ouverture volontaire d’un projet, proposition limitée, consentement à la projection publique, acceptation du propriétaire et création/rattachement d’un fil sans doublon. Voir ADR-006 et docs/product/COUPS_DE_MAIN.md. C’est une évolution HAAS décidée avec l’utilisateur, pas un nouvel ordre de l’organisateur.

## Contenu
- HAAS_CODEX_MASTER.md et AGENTS.md : instructions et plan de développement.
- docs/execution/tasks.json : **122 lots**, **106 identifiants antérieurs conservés**, 16 nouveaux lots testables.
- docs/product et docs/api : spécifications, règles de données et contrat Coup de main.
- docs/design : **23 familles d’écrans**, tokens et critères de lisibilité.
- docs/quality : **90 scénarios d’acceptation** (AC47 conditionnel) et portes de validation.
- .agents/skills : **10 skills** HAAS ciblés.
- livrables : cahier PDF/HTML et présentation PowerPoint/PDF régénérés.

## État du développement
Le socle Laravel/PostgreSQL B01–B06 est intégré dans `main` : bootstrap, qualité, CI PHP 8.4/8.5, contrat HTTP, identité et inscription. Les PR #8/#9 ont été fusionnées le 2 octobre 2026 sur demande explicite, avec CI post-fusion verte. Voir [la preuve d'intégration](docs/quality/MERGE_B05_B06.md), [le backend](backend/README.md) et [les commandes](docs/COMMANDS.md).

B07 prépare [les sessions Sanctum et CORS](docs/api/SESSIONS.md) sur `backend/socle-auth-sessions` ; [preuves B07](docs/quality/B07_SESSIONS.md). Les courriels de compte, le profil privé et les fonctionnalités métier restent à développer. Les conditions réelles doivent être configurées avant ouverture des inscriptions. Aucune application React, exécution de laboratoire ou mise en production n'est encore validée.

## Démarrer sans écraser
Équipe de trois : voir [la répartition backend, les branches et les règles de fusion](docs/execution/BACKEND_A_TROIS.md).

Lire README_CODEX.md puis DEMARRER_CODEX.txt. Décompresser hors du dépôt ; fusionner après revue. Backend complet et testé → revue + GO_FRONTEND humain → frontend → recette + GO_PRODUCTION humain. Un VPS Systalink + Vercel, PostgreSQL local, runner limité et Qodana Ultimate restent retenus. Aucun achat ou push autorisé par ce ZIP.

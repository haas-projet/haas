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

## Ce que ce ZIP n’est pas
Ce dossier ne contient pas une application Laravel/React implémentée. Les plans, tests attendus et configurations doivent être réalisés sur le dépôt. Aucun commit, CI applicative, résultat de laboratoire, utilisateur réel ou déploiement n’est attesté par la génération du pack.

## Démarrer sans écraser
Lire README_CODEX.md puis DEMARRER_CODEX.txt. Décompresser hors du dépôt ; fusionner après revue. Backend complet et testé → revue + GO_FRONTEND humain → frontend → recette + GO_PRODUCTION humain. Un VPS Systalink + Vercel, PostgreSQL local, runner limité et Qodana Ultimate restent retenus. Aucun achat ou push autorisé par ce ZIP.

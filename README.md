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

Le socle **B01–B09, B12–B13 et B32** est intégré dans main : Laravel/PostgreSQL, qualité/CI, HTTP, identité/inscription, sessions/CSRF/CORS, courriels/reset, compte et permissions, audit, idempotence, administration et révocation. Les PR #10/#11/#13–#20 ont été fusionnées le 3 octobre 2026 sur demande explicite. [Preuve des fusions](docs/quality/MERGE_SOCLE.md) : main `a051e81`, [CI 37146178657](https://github.com/haas-projet/haas/actions/runs/37146178657) verte, **384 tests / 3702 assertions par PHP 8.4/8.5** avec PostgreSQL.

Les profils B10, notifications B29, signalements/modération B30–B31 et préparation B39–B42 sont intégrés pour leur périmètre disponible, mais restent **partiels**. Contributions, événements et adaptateurs des domaines des autres pilotes, recette transversale, Qodana et exploitation réelle restent ouverts. [Réception partielle](docs/quality/SOCLE_RECEPTION_PARTIELLE.md), [blocages](docs/BLOCKERS.md) et [tableau des 22 tâches du socle](docs/execution/participants/ousseynoufayeisidk-sys/SYSTALINK_TASKS.md).

Quatre branches permanentes sont conservées : main, backend/socle-auth, backend/communaute-entraide et backend/capsules-laboratoire. Les neuf branches temporaires du socle ont été supprimées après vérification de leurs commits dans main. Les branches des collègues et la PR #12 sont préservées.

Lire [les commandes](docs/COMMANDS.md) et [le backend](backend/README.md). Les conditions d'inscription, SMTP et domaines réels restent à configurer/vérifier avant ouverture. BACKEND_GATE NON REÇU, aucun GO_FRONTEND ni déploiement validé.

## Démarrer sans écraser
Équipe de trois : voir [la répartition backend, les branches et les règles de fusion](docs/execution/BACKEND_A_TROIS.md).

Lire README_CODEX.md puis DEMARRER_CODEX.txt. Décompresser hors du dépôt ; fusionner après revue. Backend complet et testé → revue + GO_FRONTEND humain → frontend → recette + GO_PRODUCTION humain. Un VPS Systalink + Vercel, PostgreSQL local, runner limité et Qodana Ultimate restent retenus. Aucun achat ou push autorisé par ce ZIP.

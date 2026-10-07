# HAAS — Help as a Service

**Rencontrez-vous en construisant quelque chose ensemble.** HAAS est une plateforme communautaire où les développeurs présentent leurs projets, posent des questions, proposent de l'aide et partagent des solutions documentées. Une question simple peut être posée sans code ni laboratoire.

Le projet est en développement : backend Laravel/PostgreSQL d'abord, puis SPA React/TypeScript après réception du backend et accord humain **GO_FRONTEND**.

## Parcours prévus

- **Échanger** : questions, commentaires et propositions structurées ; l'auteur décide de la résolution de sa demande.
- **Se rencontrer** : projets et annuaire volontaire de développeurs.
- **Donner un coup de main** : projet ouvert volontairement, offre privée, décision du propriétaire et deux consentements avant projection publique. L'acceptation ouvre une collaboration.
- **Partager et vérifier** : capsules documentées, revue indépendante, versions publiées immuables et comparaison de deux implémentations approuvées sur des données fictives. Aucun code utilisateur n'est exécuté dans le laboratoire.

Ces parcours définissent le produit à livrer. Leur avancement est détaillé ci-dessous et dans [le suivi d'exécution](docs/execution/PROGRESS.md).

## État du développement — 7 octobre 2026

| Périmètre | État réel |
|---|---|
| Socle B01–B09, B11–B13 et B32 | **13 lots intégrés dans `main`** : Laravel, qualité/CI, contrat HTTP, identité, authentification par session, courriels/reset, compte et permissions, schéma de collaboration, audit, idempotence et administration. |
| Demandes et commentaires B14–B17 | **4 lots intégrés dans `main` par #24** : création, lecture/recherche, édition/publication versionnée, commentaires historisés et notifications après commit. |
| Profils B10, notifications B29, modération B30–B31 et préparation B39–B42 | Livraisons partielles intégrées ; raccordements métier et réception transversale à compléter. |
| Capsules et laboratoire | Enums intégrés par #12 ; B22/B23/B24 en préparation dans les PR #29/#30/#33. |
| Brique interne B1 — B35 | Service transactionnel, déduplication, tests de concurrence et correction UTC intégrés par #27. Connexion/runtime laboratoire séparés et module pédagogique défectueux à compléter. |
| API de démonstration B2 — B38 | PR #28 : isolation corrigée dans le travail de revue, validations finales en cours avant approbation. Livraison distincte de `main`. |
| Frontend React/TypeScript | À développer après **BACKEND_GATE** et **GO_FRONTEND** ; `frontend/` contient les consignes. |

Les PR [#12](https://github.com/haas-projet/haas/pull/12), [#23](https://github.com/haas-projet/haas/pull/23) (avec les compléments #22) et [#27](https://github.com/haas-projet/haas/pull/27) sont fusionnées dans `main`. Le code B1+B11 combiné a passé **488 tests / 3 977 assertions**, localement puis sous PHP 8.4/8.5 et PostgreSQL 17 dans la [CI 37637092346](https://github.com/haas-projet/haas/actions/runs/37637092346). La [CI de `main` après fusion](https://github.com/haas-projet/haas/actions/runs/37637569825) est également verte.

B14–B17 sont désormais fusionnés par [#24](https://github.com/haas-projet/haas/pull/24), commit `b76612d`. Le head publié et main ont chacun une CI verte sur PHP 8.4/8.5 : **671 tests / 6 805 assertions** par version, PostgreSQL compris. Voir la [CI de main 37681824151](https://github.com/haas-projet/haas/actions/runs/37681824151) et le [bilan de préparation des PR](docs/quality/PR_READINESS_20261007.md).

Preuves : [revue et fusions de Madina](docs/quality/MERGE_MADINA.md), [fusions du socle](docs/quality/MERGE_SOCLE.md), [réception partielle](docs/quality/SOCLE_RECEPTION_PARTIELLE.md) et [blocages du socle](docs/BLOCKERS.md).

**BACKEND_GATE non reçu.** La recette complète, Qodana et l'exploitation réelle restent à valider. Les conditions d'inscription, SMTP et domaines réels restent à configurer et vérifier avant ouverture. Aucun **GO_FRONTEND** ni **GO_PRODUCTION** reçu.

## Démarrer le backend en local

Prérequis : **PHP 8.4 minimum** avec `pdo_pgsql`, **Composer 2** et **PostgreSQL 17**. Node.js sert aux contrôles documentaires ; les versions observées sont consignées dans [VERSIONS.md](docs/VERSIONS.md).

Depuis un nouveau clone, exemple PowerShell :

```powershell
git clone https://github.com/haas-projet/haas.git
cd haas/backend
composer install
composer check-platform-reqs
if (!(Test-Path .env)) {
    Copy-Item .env.example .env
    php artisan key:generate
}
```

Créer une base PostgreSQL locale de développement, puis renseigner sa connexion et les origines locales dans `backend/.env`. Vérifier la cible avant d'appliquer les migrations :

```powershell
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

L'URL locale par défaut est `http://localhost:8000`, cohérente avec `APP_URL` ; adapter les origines configurées si cette URL change. Servir uniquement `backend/public`. `GET /up` contrôle le démarrage du backend ; la disponibilité SQL et les services externes demandent leurs propres vérifications. Consulter [COMMANDS.md](docs/COMMANDS.md) pour sélectionner PHP, configurer l'environnement et utiliser les commandes d'exploitation.

## Contrôles disponibles

Depuis `backend/` :

```powershell
composer lint
composer analyse
composer test
```

Les tests PostgreSQL s'exécutent avec `composer test:integration` après configuration d'une **base locale/CI jetable dédiée** nommée `haas_*_test`, avec le rôle `haas_test`. Les paramètres et garde-fous sont décrits dans [COMMANDS.md](docs/COMMANDS.md#tests).

Depuis la racine : `node scripts/validate-pack.mjs`, `node scripts/check-deployment-docs.mjs` et `php scripts/generate-api-types.php --check`. GitHub Actions exécute les contrôles backend sur PHP 8.4/8.5 avec PostgreSQL 17 ; consulter les checks du dernier commit de chaque PR.

## Organisation et documentation

Trois domaines sont coordonnés dans [BACKEND_A_TROIS.md](docs/execution/BACKEND_A_TROIS.md) : socle/intégration (`ousseynoufayeisidk-sys`), communauté/entraide (`LamineGL`), capsules/laboratoire (`mdev44-code`). Les branches permanentes de chaque domaine et `main` sont conservées. Chaque lot avance par petite PR, avec prérequis intégrés, revue humaine et contrôles vérifiés avant fusion.

| Ressource | Contenu |
|---|---|
| [AGENTS.md](AGENTS.md), [brief maître](HAAS_CODEX_MASTER.md) | Consignes et ordre de développement. |
| [PROGRESS](docs/execution/PROGRESS.md), [HANDOFF](docs/execution/HANDOFF.md), [tasks.json](docs/execution/tasks.json) | Reprise et 122 lots ordonnés ; 106 identifiants historiques conservés. |
| [Produit](docs/product/), [API](docs/api/) | Règles métier, contrats et [parcours Coup de main](docs/product/COUPS_DE_MAIN.md). |
| [Design](docs/design/), [qualité](docs/quality/) | 23 familles d'écrans, 90 scénarios d'acceptation (AC47 conditionnel), preuves et gates. |
| [Backend](backend/README.md), [skills HAAS](.agents/skills/) | Architecture des couches et 10 skills ciblés. |
| [Livrables](livrables/) | Cahier, supports de présentation et exports du dossier de conception. |

## Hébergement retenu

L'architecture prévoit **un VPS Systalink** pour Laravel/PostgreSQL et un service laboratoire local restreint, **Vercel** pour React et une sauvegarde distante. La configuration cible limite le laboratoire à un run actif global ; les enfants d'une comparaison s'exécutent séquentiellement. B2 doit utiliser une origine et des données fictives séparées des cookies HAAS.

Lire [l'architecture de déploiement](docs/deployment/ARCHITECTURE_DEPLOIEMENT.md) et [l'authentification/CORS/Sanctum](docs/deployment/AUTH_CORS_SANCTUM.md). La mise en production demande une revue et **GO_PRODUCTION** : API compatible d'abord, frontend ensuite.

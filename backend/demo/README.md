# API B2 — runtime séparé

Ce backend enregistre uniquement des commandes fictives `demo-0000` à `demo-9999`, de 1 à 1 000 000 unités mineures, en EUR fictifs. Aucun paiement ni compte HAAS. Le frontend, IndexedDB et les tests de coupure navigateur restent à livrer après GO_FRONTEND.

## Configuration locale ou CI

Utiliser PHP 8.4/8.5 et les dépendances verrouillées de `backend/vendor`. `demo/public/index.php` et `demo/artisan` démarrent une base Laravel distincte de HAAS. Copier seulement `.env.example` de ce dossier vers son propre `.env`, puis renseigner les variables `DEMO_*`. Ne pas copier l'environnement HAAS. Générer `DEMO_IDEMPOTENCY_KEY` avec `bin2hex(random_bytes(32))`, conserver sa valeur hors du dépôt et ne pas l'afficher dans les journaux.

La connexion `demo` est la seule configurée. Son rôle PostgreSQL non privilégié possède uniquement la base fictive ; retirer CONNECT à PUBLIC sur la base métier et n'accorder aucun membership métier au rôle fictif. `APP_KEY`, les anciens secrets, sessions, SMTP et connexions `DB_*` HAAS ne configurent pas B2. Les caches de configuration/routes/providers et le stockage restent sous ce dossier, même si l'environnement contient des overrides Laravel HAAS.

Depuis `backend/`, commandes du runtime B2 :

```powershell
php demo/artisan migrate --force
php demo/artisan route:list
php demo/artisan demo:prune
php demo/artisan schedule:list
```

`migrate` ne découvre que `demo/database/migrations`. La purge est planifiée toutes les quinze minutes par le scheduler B2. Le cache/quota partagé utilise PostgreSQL (`DEMO_CACHE_STORE=database`), jamais la base métier. Le mode `array` est réservé aux tests sans SQL.

## HTTP

Configurer une API et une origine frontend exactes hors du parent de cookies HAAS. Les domaines `demo-api.example.com` et `demo.example.com` sont des exemples sans site configuré. Le runtime refuse un hôte API différent, les origines/Referer inconnus, Cookie et Authorization ; aucune réponse n'émet de cookie. POST JSON, corps maximum 2048 octets, uniquement les trois champs documentés. CORS n'autorise aucun credential ni wildcard.

`POST /api/v1/b2/demo-orders` exige une clé UUID v4 stable. Création 201, rejeu identique 200, divergence 409. Garantie pendant 24 heures : à expiration, une nouvelle confirmation peut être créée avec cette clé ; le futur client doit expliquer cette limite. Une nouvelle clé représente une nouvelle intention. Quota de trente POST par minute par adresse IP et maximum de 5000 commandes stockées. Purge bornée à 1000 commandes par invocation ; aucune intention encore garantie n'est purgée prématurément.

Contrat séparé : [DEMO_OPENAPI.yaml](../../docs/api/DEMO_OPENAPI.yaml). L'API HAAS ne sert aucune de ces routes.

## Réception et hébergement

Sur le même VPS prévu par ADR-004 : artefact et environnement propres, compte/pool PHP distinct, vhost dont la racine publique est exclusivement `demo/public`, rôle/base fictifs et stockage/cache séparés. Le compte/pool B2 ne doit pouvoir lire ni l'environnement ni les fichiers privés HAAS ; borner aussi les chemins PHP (`open_basedir`) et les droits du compte système. L'artefact B2 exclut l'environnement, caches, stockage privés, configuration et points d'entrée HAAS.

Ces restrictions système et les domaines réels restent à vérifier sur l'environnement retenu après revue et GO_PRODUCTION. Les preuves locales PostgreSQL/processus ne déclarent pas un déploiement Systalink ou une isolation OS déjà reçue.

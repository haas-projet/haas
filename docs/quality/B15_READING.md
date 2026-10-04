# B15 — Preuves de lecture et recherche des demandes

4 octobre 2026. Branche `backend/communaute-entraide-b15` depuis B14 `6a0db1e`, préparée séparément à la demande de continuer. Travail de Madina B11 et PR #12 conservés ; prérequis #23 → #22 → #24 non fusionnés. Le fichier capsules-lab.php modifié dans le répertoire principal n'est ni réécrit ni inclus. Pas de migration, dépendance ou frontend ajouté.

## Périmètre livré

GET liste et détail, Request/DTO, Queries de visibilité partagée et Policy de détail, Resource B14 réutilisée et collection paginée commune. Contrat [HELP_REQUEST_READING.md](../api/HELP_REQUEST_READING.md), OpenAPI 0.14.0 et 38 types générés. Public sans brouillons, vue mine contrôlée, filtre état/technologie, recherche littérale bornée et tri stable. Compteurs calculés après visibilité.

## Environnement et commandes réelles

Windows, PHP 8.5.10, PHPUnit 12.5.37, PostgreSQL 17.0. Cluster dédié local `.worktrees/b15-postgres`, port 54695, base `haas_b15_test`, rôle `haas_test`, écoute 127.0.0.1 ; créé par initdb après constat que l'ancien cluster temporaire n'existait plus. Le garde TestDatabaseGuard s'exécute avant connexion/migration. Aucun accès à une base applicative ni au fichier privé de Madina.

Depuis `.worktrees/b15/backend`, PHP 8.5 sélectionné dans PATH et variables DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, DB_PORT=54695, DB_DATABASE=haas_b15_test, DB_USERNAME=haas_test, DB_PASSWORD vide, DB_URL vide :

```text
php vendor/bin/phpunit tests/Integration/HelpRequests/ReadHelpRequestsTest.php
php vendor/bin/phpunit --testsuite Unit,Feature,Architecture
php vendor/bin/phpunit --testsuite Integration
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --memory-limit=512M --no-progress
php C:/ProgramData/ComposerSetup/bin/composer.phar validate --strict --no-check-publish --no-ansi
php C:/ProgramData/ComposerSetup/bin/composer.phar check-platform-reqs --no-ansi
php C:/ProgramData/ComposerSetup/bin/composer.phar audit --locked --no-interaction --no-ansi
```

Depuis la racine du worktree :

```text
php scripts/generate-api-types.php --check
node scripts/validate-pack.mjs
node scripts/check-deployment-docs.mjs
git diff --check
```

## Résultats

- Ciblés B15 : 35 tests / 290 assertions réussis ; le contrôle HTTP sans appel réseau a été ajouté ensuite et est inclus dans la suite complète ci-dessous.
- Unit/Feature/Architecture : 284 tests / 2811 assertions réussis ; inventaire réel des deux nouvelles routes et contrat vérifiés.
- Suite PostgreSQL complète : **227 tests / 2023 assertions réussis** en 3 min 49,531 s. Total distinct : **511 tests / 4834 assertions**. Le cluster dédié est arrêté après validation.
- Pint et PHPStan niveau 8 : réussis. Validation et prérequis Composer réussis, audit sans avis de vulnérabilité ; Composer local 2.8.5 émet des dépréciations sous PHP 8.5, pas une erreur de l'application.
- Types : 38 à jour. Documentation pack 18/18 et déploiement 7/7 réussis ; ces contrôles ne testent pas un serveur déployé.
- SHA256SUMS : régénéré puis vérifié sur les fichiers livrés après les mises à jour du suivi.

## Scénarios vérifiés

Visiteur et compte non vérifié : lecture publique possible ; vue mine exige un membre actif vérifié. Membre/modérateur/admin tiers : aucun brouillon d'autrui. Contenu masqué/auteur suspendu ou non vérifié absent du détail, recherche, filtre technologique et total. Masquage/suspension après une première lecture et changement de compte ne conservent pas l'accès. UUID invalide/inconnu et ressource inaccessible : 404 commun.

Les filtres se combinent sans sortir de la visibilité, y compris recherche OR sur technologies. Les caractères %, _ et ! sont littéraux ; chaînes SQL et champs privés ne donnent aucun résultat. Validation des tris/champs inconnus/page_size, taille >50 et dépassement d'entier ; pagination par défaut 20, page vide et bornes 50. Tous les états publiés restent consultables, archives comprises ; draft demeure privé. Départage par id stable, tris newest/oldest/updated.

Sur un jeu de 25 auteurs/demandes, chargement de 5 puis 25 éléments : exactement **4 SELECT métier dans chaque cas** (compte, demandes, auteurs, technologies), lazy loading interdit. Ce contrôle de N+1 ne constitue pas une mesure de latence de production. Contrat de Resource identique au POST B14 et corps de code retourné comme texte JSON ; absence de nouvelle écriture de révision/idempotence à la lecture.

## Limites et reprise

CI du SHA final à vérifier après publication et rapporter dans la PR et le bilan ; aucune revue humaine ou fusion présumée. Recherche ILIKE simple, performances de production non mesurées, casse dépendante de la collation PostgreSQL. Aucun moteur de recherche externe. La vérification d'affichage inerte dans un navigateur attend GO_FRONTEND.

B15 ne livre pas l'édition B16, les commentaires/propositions ni leurs compteurs. Les demandes liées à un projet et la visibilité de leur parent attendent BC04/BC05 ; leur modèle n'est pas simulé. Pas de nouvelle interface de modération, AC15 capsules/kit non reçu et AC25 reste partiel. B11/B14/B15 restent En cours dans Systalink jusqu'à revue/intégration vérifiée ; prochain lot B16.

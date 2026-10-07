# B22 — préparation du schéma à la revue humaine

Date : 7 octobre 2026. PR #29, travail initial `58fe552` conservé ; intégration normale de main `e3bd34c` dans le merge local `39d923c`. Les trois historiques documentaires conflictuels ont été conservés. Aucune approbation humaine, fusion distante ou réception du backend réalisée par cette intervention.

## Corrections

Le retrait d'une version publiée conservant sa date a été reproduit : 1 test / 0 assertion, erreur réelle `capsule_versions_published_at_requires_state`. La migration additive B22 permet ce retrait et préserve date, corps, limites, label, relecteur et provenance. Elle interdit les modifications ou suppressions de versions publiées/retirées, y compris via la cascade du parent. Attributions et technologies publiées sont également figées ; le relecteur ne peut être le propriétaire ou un contributeur de cette version.

La FK du relecteur devient `RESTRICT` pour garder son attribution. `lock_version` reste positif. Les deux migrations déjà présentes dans B23 (pivot de technologies et verrou) sont reprises à leurs chemins exacts avec un contenu identique, sans créer de tables concurrentes ni réécrire les migrations initiales partagées. Les modèles protègent les champs serveur contre l'assignation de masse. Les Services B23 consommateurs doivent utiliser une affectation serveur explicite après autorisation et verrouillage.

La FK de source et le XOR restent présents. L'état « résolu », l'accès et la stabilité de la résolution relèvent de B23 ; ce schéma ne remplace pas ce contrôle. Les kits, le laboratoire et la publication HTTP ne sont pas livrés en B22. Contrat détaillé : [CAPSULE_DATA.md](../architecture/CAPSULE_DATA.md).

## Environnement et commandes

PHP local 8.5.10 (`C:\laragon\bin\php\php-8.5.10-nts-Win32-vs17-x64\php.exe`), Laravel 13.34.0, PostgreSQL 17 local `127.0.0.1:55447`, rôle `haas_test`, base dédiée **`haas_b22_review_test`**. `TestDatabaseGuard` accepté ; aucune base applicative/service sur 5432 ciblé. Clé de test éphémère dans `.env.testing` ignoré. Vendor copié indépendamment depuis le lockfile identique ; aucune dépendance ajoutée.

Pour les commandes SQL, les variables `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=55447`, `DB_DATABASE=haas_b22_review_test`, `DB_USERNAME=haas_test`, `DB_PASSWORD` vide et `DB_URL` vide ont été fournies au processus. Le binaire PHP ci-dessus exécute les scripts `vendor/bin/*` depuis `backend/`.

| Commande | Résultat réellement observé |
|---|---|
| `vendor/bin/phpunit --testsuite Unit,Feature,Architecture` | 319 tests / 2570 assertions réussis, relance finale comprise |
| `vendor/bin/phpunit tests/Integration/Capsules/PublishedCapsuleIntegrityTest.php --stop-on-error` | 15 tests / 32 assertions réussis avant ajout des 2 cas de migration |
| Même fichier, filtre `test_additive_upgrade\|test_rollback_refuses` | 2 tests / 7 assertions réussis après correction des fixtures |
| `vendor/bin/phpunit --testsuite Integration` | **232 tests / 1587 assertions réussis**, JUnit : 0 erreur / 0 échec |
| `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Niveau 8 : aucune erreur après correction d'un PHPDoc de test |
| `vendor/bin/pint --test` | Réussi |
| `composer validate --strict --no-check-publish`, `check-platform-reqs`, `audit --locked` via le binaire PHP adapté | Valide, plateforme compatible, aucune alerte de sécurité ; dépréciations propres à Composer 2.8 sous PHP 8.5 |
| `node scripts/validate-pack.mjs` | 18/18, documentation uniquement |
| `node scripts/check-deployment-docs.mjs` | 7/7, documentation uniquement |
| PHP 8.5 : `scripts/generate-api-types.php --check` | 28 types API à jour ; aucune route B22 |
| `git diff --check` | Sans erreur |

Incidents conservés : première cible 49 tests / 92 assertions / 17 erreurs de rollback de fixtures (table déjà déposée/fonction SQL laissée), corrigée par `down()` tolérant les tables absentes et fonctions remplaçables ; cible suivante 56 / 140 avec 1 erreur et 1 échec de fixtures (instance de migration anonyme et représentation d'horodatage/default SQL), corrigée puis revalidée. Ces appels ne sont pas comptés comme réussis.

Le correctif de navigateur de tests déjà vérifié dans B17 `8234470` est repris sur trois fichiers : expiration réelle des cookies selon l'horloge simulée, collecte de sessions forcée après 23 h et appel forgé conservant le cookie expiré attendu 401. Aucun contrôle CSRF ou code de production n'est assoupli.

La suite SQL finale revalide ce correctif et toutes les protections du schéma. Le JUnit y compte **56 tests / 148 assertions** pour les classes B22. Total unique hors SQL + SQL : **551 tests / 4157 assertions réussis**. Les relances et filtres intermédiaires ne sont pas ajoutés à ce total. Les journaux/JUnit restent locaux et ignorés, les clés de test ne sont pas versionnées.

## Limites de réception

Revue humaine et CI du futur SHA publié : en attente. PHP 8.4 n'est pas exécuté localement. Aucun Qodana, frontend, hébergement, déploiement, BACKEND_GATE ou GO_FRONTEND déclaré. AC12/AC13 complets attendent B24/B25 et les parcours API ; B22 est un lot de schéma prêt à examiner après contrôles, pas une publication de capsule déjà disponible.

## Revalidation avec main intégrant B14–B17

Après le correctif local `2148322`, le code de main `b76612d1b6587119127fd364244f5248f05f1ff2` est intégré normalement par l'intégrateur avant publication de B22. La combinaison est réellement vérifiée sur la même base dédiée `haas_b22_review_test` et le même PHP 8.5.10 :

| Commande | Résultat combiné |
|---|---|
| `vendor/bin/phpunit --testsuite Unit,Feature,Architecture` | **335 tests / 3595 assertions réussis** |
| `vendor/bin/phpunit --testsuite Integration` | **399 tests / 3390 assertions réussis**, 10 min 48 s |
| `vendor/bin/pint --test` | Réussi |
| `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Niveau 8 : aucune erreur |

Total unique du code combiné : **734 tests / 6985 assertions réussis**. Les résultats précédents de 551 / 4157 restent la preuve historique avant cette intégration ; ils ne sont pas ajoutés à ce total. Journaux et JUnit ignorés : `backend/storage/logs/b22-main-integration.*`. La résolution du conflit du test de migration d'identité garde le dépôt des tables consommatrices de capsules et le retrait des révisions de demandes/commentaires. Aucun test ou droit n'a été assoupli pour obtenir ce résultat. Le SHA de fusion doit être relevé après création par l'intégrateur ; revue humaine et CI du SHA publié restent distinctes de ces contrôles locaux.

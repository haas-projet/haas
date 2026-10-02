# B02 — Qualité PHP locale

Date : 1er octobre 2026. Base : `1735a69a9b2155bdb152d442904e8715dfa15a6f`. Branche : `backend/socle-auth`. État : IN_REVIEW, sans revue humaine simulée. Le commit de livraison réel figure dans la PR et le bilan.

## Changements

Pint 1.32.1 avec preset Laravel ; PHPStan 2.2.16 et Larastan 3.12.2 au niveau 8, PHP cible 8.4. Analyse de app, bootstrap applicatif, config, database, routes et tests. Aucune baseline, aucun ignore d'erreurs. Caches locaux ignorés.

Scripts Composer lint, format, analyse, test, test:architecture et test:integration. Le contrôle d'architecture analyse Data/Services via PHP-Parser, résout les alias et rejette les références HTTP et helpers connus. Les tests vérifient les imports simples/groupés/aliasés, façades, types pleinement qualifiés et helpers, ainsi que l'absence de faux positif sur commentaires et chaînes. Aucun fichier analysé n'est exécuté. Références dynamiques et dépendances indirectes restent à relire humainement.

Les diagnostics initiaux ont conduit à typer les données de test et à vérifier que APP_URL est une chaîne non vide avant rtrim dans filesystems.php. Une configuration invalide produit un message neutre sans révéler la valeur. Pint a uniquement réordonné les imports de User.php et health.php. Aucun changement de contrat HTTP ni fonctionnalité d'authentification livré.

## Recette observée

PHP 8.5.10, Composer 2.10.3 temporaire vérifié, PostgreSQL 17.0. Les commandes Composer ci-dessous ont été invoquées avec ce PHP et ce PHAR explicites ; l'installation globale n'a pas été modifiée.

| Contrôle | Résultat |
|---|---|
| `composer install --no-interaction --no-progress --no-plugins` | Réussi depuis le lockfile, découverte Laravel comprise |
| `composer format` puis `composer lint` | Corrections d'import appliquées puis formatage conforme |
| `composer analyse` | Niveau 8, aucune erreur |
| `composer test` | 22 tests / 28 assertions réussis, sans base |
| `composer test:architecture` | 1 test / 1 assertion réussi, déjà inclus dans composer test |
| `composer test:integration` | 1 test / 5 assertions réussis sur PostgreSQL réel |
| Total des suites distinctes | **23 tests, 33 assertions** |
| `composer validate --strict --no-check-publish` | Réussi |
| `composer check-platform-reqs` | Prérequis satisfaits |
| `composer audit --locked` | Aucun avis signalé au moment du contrôle |
| `node scripts/validate-pack.mjs` | 18/18 contrôles documentaires réussis |
| `node scripts/check-deployment-docs.mjs` | 7/7 contrôles documentaires réussis |
| `git diff --check` | Sans erreur |

Cluster PostgreSQL dédié créé pour cette recette : `127.0.0.1:54681`, base `haas_quality_test`, rôle `haas_test`. Migrations, UUID et FK testés. Une première tentative de lancement a été arrêtée après un code de sortie Windows non exploitable ; la reprise a vérifié pg_isready avant de créer la base et de tester. Le serveur temporaire est arrêté ; le service existant sur 5432 reste inchangé. Le dernier ajout au contrôle de la façade HTTP a été revérifié par lint/analyse/tests sans modifier les migrations ou les tests SQL.

## Vérification négative réelle

Un fichier temporaire `app/Data/B02QualityWitness.php` a été ajouté avec un formatage incorrect, un retour string déclaré int et une Request importée sous alias. Résultats : lint sort avec code 1, analyse détecte return.type et sort avec code 1, test:architecture détecte Illuminate\Http\Request et sort avec code 1. Le fichier a été retiré dans un finally ; les contrôles normaux repassent ensuite. Aucun témoin incorrect livré ni contrôle désactivé.

## Limites et suite

Les licences des quatre nouveaux paquets MIT ont été lues ; les 101 dépendances précédentes gardent leurs versions. Inventaire : [B02_DEPENDENCIES.json](B02_DEPENDENCIES.json). Les preuves B01 restent historiques.

PHP 8.4 natif, GitHub Actions et Qodana : non exécutés. B03 doit fournir la CI réelle, notamment sur PHP minimal. B02 n'est ni une validation du backend P0 ni un GO_FRONTEND. Prochain lot après revue et intégration : B03, puis B04/B05 et authentification.

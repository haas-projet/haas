# B01 — Preuves du socle Laravel / PostgreSQL

Date : 1er octobre 2026. Branche : `backend/socle-auth`. État : vérifié localement, en attente de revue humaine. Le SHA réel du commit contenant ce rapport figure dans la PR et le bilan, sans autoréférence inventée.

## Livraison

Squelette officiel adapté en API, dépendances verrouillées pour PHP 8.4 minimum, PostgreSQL explicite, fuseau UTC, utilisateur UUID et référence de session compatible. Aucun compte de démonstration créé par défaut. `/up` est minimal, sans session ni SQL. Trois fichiers de routes métier enregistrés sous `/api/v1`, sans endpoints factices. Consignes backend et dossiers de l'équipe conservés.

## Résultats observés

| Commande / contrôle | Résultat |
|---|---|
| Installation initiale depuis composer.lock, sans scripts/plugins | 101 paquets installés, licences inventoriées dans B01_DEPENDENCIES.json |
| `php artisan --version` / `package:discover` | Laravel 13.34.0 ; découverte réussie |
| `php vendor/bin/phpunit` avec connexion dédiée explicite | **12 tests, 22 assertions, aucun échec** |
| Unit/Feature | 11 tests : refus des cibles SQL dangereuses, health sans cookie ni base, erreur API inconnue sans trace interne |
| Intégration PostgreSQL | 1 test réel : migrations, UUID Eloquent/SQL et FK de session après suppression |
| `php artisan migrate:status` sur la base de test | Trois migrations réellement appliquées : users/sessions/reset, cache, jobs |
| Requête HTTP réelle sur serveur PHP temporaire | `/up` : **200**, corps exact `{"status":"ok"}` |
| `php artisan route:list --json` | Une route GET/HEAD `/up` ; aucune route de fichiers temporaires |
| `php -l` | 36 fichiers PHP contrôlés, aucune erreur |
| `composer validate --strict --no-check-publish` | Réussi |
| `composer check-platform-reqs` sous PHP 8.5.10 | Prérequis satisfaits |
| `composer audit --locked` | Aucun avis de vulnérabilité signalé au moment du contrôle |
| `node scripts/validate-pack.mjs` | 18/18 contrôles documentaires réussis |
| `node scripts/check-deployment-docs.mjs` | 7/7 contrôles documentaires réussis |
| `git diff --check` et contrôle de `backend/AGENTS.md` | Sans erreur ; consignes backend inchangées |

Une première exécution a révélé DB_URL et une cible SQL héritées de Windows ; le garde-fou a refusé l'accès avant migration. Recette finale uniquement sur un cluster PostgreSQL 17.0 temporaire neuf : `127.0.0.1:56954`, base `haas_bootstrap_test`, rôle `haas_test`. Le service existant sur 5432 n'est pas la cible des tests. Serveurs PostgreSQL temporaire et HTTP arrêtés après vérification.

## Limites et prochain lot

PHP 8.4 natif, CI distante, Pint/PHPStan, Qodana et hébergement : **non exécutés/non vérifiés**. Composer 2.8.5 émet des dépréciations sous PHP 8.5 ; les tests applicatifs passent. B02 qualifie les outils qualité, B03 ajoute la CI et couvre la version PHP minimale.

User et les migrations sont une base technique. Les rôles/états/profils et identifiants normalisés attendent B05 ; inscription, connexion, Sanctum/CSRF et courriels attendent B06–B09. B04 précisera les erreurs HAAS. Aucun BACKEND_GATE ni GO_FRONTEND accordé.

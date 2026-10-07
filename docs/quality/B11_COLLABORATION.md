# B11 — Schéma de collaboration

## Livraison du 3 octobre 2026

Reprise demandée par l'utilisateur de la partie de Lamine. Branche permanente `backend/communaute-entraide`, issue [#2](https://github.com/haas-projet/haas/issues/2). Le socle `main` à `7a8c672` et les commits B11 publiés par `mdev44-code` à `1c380c4` ont été intégrés sans réécriture dans le merge local `93009c3`. Les auteurs et la branche source sont conservés ; la cession B11 rapportée dans le suivi de mdev44-code ne constitue pas une revue humaine de cette livraison.

Le [contrat des données](../architecture/COLLABORATION_DATA.md) décrit les cinq tables, relations, états, FK, index partiel et limites. Les migrations existantes restent inchangées. Une migration additive apporte les versions des commentaires/propositions et les CHECK de positivité, en conservant les données déjà présentes. La date d'acceptation n'est plus assignable en masse ; la factory utilise l'auteur de la demande comme acceptant par défaut.

## Contrôles réellement exécutés

Environnement local : PHP 8.5.10, PostgreSQL 17.0 temporaire sur `127.0.0.1:54694`, base `haas_community_test`, rôle `haas_test`, `APP_ENV=testing` imposé par PHPUnit. Aucun service PostgreSQL habituel ni base applicative utilisé. Les commandes ci-dessous sont lancées dans `backend/` avec ce PHP en tête du PATH et les variables DB explicites.

| Commande | Résultat |
|---|---|
| `php vendor/bin/pint --test` | Réussi |
| `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Aucune erreur, sans baseline ajoutée |
| `php vendor/bin/phpunit --testsuite Unit,Feature,Architecture` | 282 tests, 2483 assertions, réussis |
| `php vendor/bin/phpunit tests/Integration/HelpRequests --testdox` | 18 tests, 66 assertions, réussis ; rejoués après les corrections des types et de la migration |
| `php vendor/bin/phpunit --testsuite Integration` | 151 tests, 1316 assertions, réussis |
| `composer validate --strict --no-check-publish --no-interaction` | Réussi ; Composer 2.8.5 émet des dépréciations sous PHP 8.5 |
| `composer audit --locked --no-interaction --format=json` | Aucune alerte, aucune dépendance abandonnée |
| `php ../scripts/generate-api-types.php --check` | 28 types à jour, aucun endpoint changé |
| Lint PHP des deux scripts API/exploitation | Réussi |
| `node scripts/validate-pack.mjs` (racine) | 18/18 |
| `node scripts/check-deployment-docs.mjs` (racine) | 7/7 |
| `git diff --check` et empreintes `SHA256SUMS` | Sans erreur ; 492 fichiers |

Les 29 nouveaux cas de protection d'assignation couvrent identifiants, auteurs, états, versions et dates serveur. Les 18 cas SQL couvrent le graphe des modèles, l'unicité du pivot, les états, la proposition étrangère, l'unicité d'une résolution active, la révocation historique, les versions positives, les suppressions interdites, la migration depuis les données existantes et le cycle d'annulation/réapplication des six migrations B11. `TestDatabaseGuard` protège l'accès à la base dédiée. L'annulation B05 existante reste dans la suite de non-régression.

Les corrections de types des tests n'ajoutent aucun ignore d'analyse. Aucun test de concurrence HTTP n'est annoncé : B19 devra tester deux acceptations réelles, sous verrou et avec les droits courants.

## Revue et limites

B11 sera proposé en revue ; la CI du commit publié reste à observer dans la PR. Aucune validation humaine ni fusion de B11 n'est présumée. Ne pas déplacer la tâche Systalink vers Terminé avant son intégration vérifiée.

B14–B21, BC01–BC08 et BH01–BH10 restent à réaliser. La création/publication de demandes, la résolution par son auteur, les filtres de visibilité et les règles de `ask_question` ne sont pas livrés par des tables seules. Prochain lot : B14 après intégration de B11, en coordonnant l'enum HelpIntent avec BV201. Aucun BACKEND_GATE, GO_FRONTEND, GO_PRODUCTION, contrôle Qodana ou déploiement n'est validé par cette livraison.

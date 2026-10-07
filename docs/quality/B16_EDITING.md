# B16 — Preuves d'édition, publication et révisions

4 octobre 2026. Branche `backend/communaute-entraide-b16`, worktree `.worktrees/b16`, depuis B15 `0cfcde1`. Reprise demandée pendant la revue ; PR #23 de Madina, #22, #24 et #25 préservées. La modification locale des routes capsules reste dans le répertoire principal et n'est pas incluse. Migration additive B16, aucune migration déjà partagée ni dépendance modifiée.

## Livraison

PATCH des champs autorisés sous version et verrou, POST de publication explicite d'un brouillon, GET d'historique paginé. Réutilisation des règles de contenu B14 (extraites sans changement de contrat) et du socle de session/CSRF/idempotence/audit. Policy auteur actif/vérifié, pas de bypass admin, archives en lecture seule. Table de révisions métier et audit minimal atomiques ; aucune copie ancienne de code/contenu dans l'historique. Notes des brouillons toujours privées après publication.

Contrat : [HELP_REQUEST_EDITING.md](../api/HELP_REQUEST_EDITING.md), OpenAPI 0.15.0 et 42 types générés. Les révisions exposent champs changés, note et date ; aucun historique de snapshots n'est revendiqué.

## Environnement et commandes

Windows, PHP 8.5.10, PHPUnit 12.5.37, PostgreSQL 17.0. Cluster dédié `.worktrees/b15-postgres`, écoute 127.0.0.1/54695, nouvelle base `haas_b16_test`, rôle `haas_test`. La base B15 existante et les bases applicatives ne sont pas ciblées. Garde TestDatabaseGuard exécuté avant toute connexion/migration.

Depuis `.worktrees/b16/backend`, PHP 8.5 dans PATH et DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, DB_PORT=54695, DB_DATABASE=haas_b16_test, DB_USERNAME=haas_test, DB_PASSWORD vide, DB_URL vide :

```text
php artisan package:discover --no-ansi
php vendor/bin/phpunit --testsuite Integration --filter 'UpdateHelpRequest|HelpRequestRevisionMigration'
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

- Unit/Feature/Architecture : 284 tests / 3071 assertions réussis ; inventaire des trois opérations et schémas vérifiés.
- Reprise ciblée des migrations B11/B05 et du parcours création → édition → publication : 8 tests / 73 assertions réussis.
- Relance ciblée B14 après un délai de connexion local PostgreSQL : 25 tests / 225 assertions réussis, sans changement applicatif ; serveur disponible, aucune session pendante constatée.
- PostgreSQL complet : 267 tests / 2444 assertions réussis après relance complète (3 min 36 s), dont 40 nouveaux cas B16 et les six courses réelles. Total distinct avec Unit/Feature/Architecture : 551 tests / 5515 assertions. Cluster local dédié arrêté, absence de serveur confirmée par pg_ctl status.
- Pint et PHPStan niveau 8 réussis. Première analyse interrompue par deux workers initialisant le cache Laravel sous Windows ; package:discover exécuté séquentiellement. Aucun ignore ou baseline ajouté.
- Composer validation/prérequis/audit réussis ; aucun avis de vulnérabilité. Dépréciations de Composer local 2.8.5 sous PHP 8.5 explicites, sans modification du lockfile.
- 42 types à jour ; documentation pack 18/18 et déploiement 7/7 réussis. Empreintes SHA256SUMS actualisées et vérifiées après résultats finaux ; diff sans erreur de blancs.

## Scénarios couverts

Propriété face à membre/modérateur/admin, accès au brouillon privé, version périmée, doublon/rejeu même charge et conflit de charge. Les champs serveur même null sont rejetés. Édition partielle conserve les valeurs absentes, remplace explicitement les technologies, revalide l'état final et les transitions help_intent. Note obligatoire après commentaire/proposition ; clarification d'une demande résolue conserve la résolution acceptée. Code et URL restent inertes. Sessions, CSRF, clé obligatoire, compte non vérifié et archive contrôlés.

Publication impossible si brouillon incomplet ; le PATCH ne publie jamais. Publication idempotente sous version et unique transition draft → open. Parcours B14 création → édition → publication avec audit ordonné 1/2/3. Historique public exclut les anciennes notes de brouillon et leurs compteurs ; propriétaire seul voit ces notes, retrait/suspension du parent interdit l'accès. Pannes SQL forcées séparément dans historique et audit : aucun changement de contenu/pivot/version/intention, relance après réparation possible.

Six courses à deux processus PHP et connexions PostgreSQL distinctes : même version/deux clés, même intention répétée, publication contre édition, arrivée d'une contribution avant obtention du verrou, suspension, masquage. Fixtures commitées et barrière pg_stat_activity observant deux workers simultanément bloqués sur verrou avant libération ; aucun test séquentiel présenté comme course. Les tests de retour B11 et B05 retirent désormais la table enfant des révisions avant leurs parents. Le test B11 descend B16/B14 puis les réapplique dans l’ordre, sans modification des migrations B11/B14. La première suite complète a relevé ces six erreurs de dépendances dans les tests ; leur reprise ciblée passe après correction. Migration montée/descendue, unicité de version, contraintes de note/action/publication et suppression de fixture avec intégrité vérifiées.

## Limites et reprise

Les contrôles applicatifs portent sur des appels HTTP en test et de vrais processus PostgreSQL. Navigateur/frontend et Qodana non exécutés pour ce lot. Les workflows B17/B18/B19 sont encore à livrer ; leurs modèles B11 servent aux fixtures. Futurs écrivains de contributions/modération : respecter le verrou parent et les règles de visibilité. Les contrôles projet attendent BC04/BC05. Pas de réception globale AC25/BACKEND_GATE ni de déploiement.

CI finale et SHA à reporter après commit dans la PR et le bilan ; aucune revue humaine ou fusion présumée. Ordre d'intégration #23 → #22 → #24 → #25 → B16. Systalink B11/B14/B15/B16 restent En cours jusqu'à intégration vérifiée. Prochain lot B17 — commentaires historisés.

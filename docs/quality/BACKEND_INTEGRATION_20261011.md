# Composition locale du backend P0 — 11 octobre 2026

## 12 octobre 2026 — Réception complète du code composé

Code `0435bdbdd7b45d4edf6465421bb58c60c5b0c694`, [CI 38177876071](https://github.com/haas-projet/haas/actions/runs/38177876071) reçue dans chacun des deux journaux PHP 8.4/8.5 : **586/5605 hors SQL + 961/8681 Integration = 1547 tests / 14286 assertions par PHP**, 82 types et 856 empreintes, trois jobs réussis. PostgreSQL local complet reçu au même code, 961/8681, zéro erreur/échec/skip. Les anciens ciblés ne sont pas additionnés à la suite complète.

| Job CI | Hors SQL | PostgreSQL | Total disjoint | Types | Empreintes |
| --- | --- | --- | --- | --- | --- |
| [PHP 8.4 / PostgreSQL 17](https://github.com/haas-projet/haas/actions/runs/38177876071/job/114584011004) | 586 / 5605 | 961 / 8681 | **1547 / 14286** | 82 | 856 |
| [PHP 8.5 / PostgreSQL 17](https://github.com/haas-projet/haas/actions/runs/38177876071/job/114584011207) | 586 / 5605 | 961 / 8681 | **1547 / 14286** | 82 | 856 |

La composition est reçue techniquement ; les PR de chaque domaine restent soumises à la revue humaine et à leurs prérequis. B20, BC02/BC06 et B33 sont préparés sur des branches distinctes ; B26 reste partiel. Le commit documentaire suivant doit recevoir sa CI exacte avant toute qualification de ce nouveau SHA ou passage de la PR à ready. Aucun BACKEND_GATE, GO_FRONTEND ou GO_PRODUCTION.

Les sections suivantes décrivent la préparation antérieure, y compris la suite alors en cours ; elles ne remplacent pas la réception ci-dessus.


## Sources et périmètre

Branche isolée `backend/integration-p0-20261011`, merge local normal B19 `39f96e94d8664bb39da4083a57fbd93ba295a643` + B27 `524067b3eda56b0f6f04098423d707f6ebee4087`. Main reste `d0d47c5dd62887fcf18361f499a33a800538b2b1`. Les PR #29/#30/#33/#34/#35/#36/#37/#38 restent ouvertes à la revue humaine dans leur ordre de prérequis ; #35/#36 conservent leur Draft et leurs limites B33/Q16/ARB05.

Les 15 conflits concernent cinq fichiers PHP (NotificationEvent, InternalNotification, NotificationResource, NotificationQuery, IdentityMigrationTest), contrats/types générés, empreintes et suivi/README. Les six événements existants restent acceptés ; les requêtes recontrôlent les droits et la visibilité actuels avant pagination/compteurs/marquage. Les deux historiques et les 122 identifiants sont préservés. Aucun producteur ou endpoint nouveau créé par ce merge.

Le checkout utilisateur reste sur sa branche B14 avec sa modification préexistante de capsules-lab.php conservée. Les tests utilisent un vendor indépendant au même composer.lock et une base locale dédiée.

## Contrôles réels

Runner depuis la racine : `node .worktrees/run-pr-review-20261011.cjs p0`, PHP 8.5.10, PostgreSQL17 `127.0.0.1:55447`, base exclusive `haas_p0_review_test`. Cluster de revue propre au workspace ; service Laragon et bases des autres lots non ciblés.

| Commande après le runner sauf mention | Résultat reçu |
| --- | --- |
| `vendor/bin/phpunit --testsuite Unit,Feature,Architecture --colors=never --log-junit storage/logs/p0-domains-nonsql-20261011.xml` | **586 tests / 5605 assertions**, zéro erreur/échec/skip |
| `vendor/bin/phpunit --testsuite Integration --colors=never --log-junit storage/logs/p0-domains-postgres-20261011.xml` | **En cours ; aucun résultat final reçu** |
| `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Niveau8, aucune erreur |
| `vendor/bin/pint --test` | Réussi |
| `../scripts/generate-api-types.php --check` | **82 types à jour** |
| `composer.phar validate --strict --no-check-publish` et `check-platform-reqs` | Valide, exigences satisfaites |
| `composer.phar audit --locked --no-interaction` | Aucun avis de vulnérabilité reçu |
| `node scripts/validate-pack.mjs`, `node scripts/check-deployment-docs.mjs` depuis le worktree | **18/18 et 7/7**, documentation seulement |
| `git diff --cached --check` | Propre après résolution |

L'audit réseau utilise le verrou existant, sans ajout de dépendance. Des avertissements de dépréciation sont émis par le PHAR Composer local sous PHP8.5 ; ils restent distincts de ses sorties réussies. Deux lectures initiales ont ciblé des noms de fichier absents, et un appel initial du générateur a utilisé un chemin helper incorrect ; les bons chemins ont ensuite donné les preuves ci-dessus. Aucun contrôle absent n'est qualifié de réussi.

## Limites et prochaine étape

Le résultat SQL complet et une CI exacte de cette composition restent à recevoir. Les CI vertes des branches sources ne sont pas une recette de leur code réuni. La branche de contrôle n'accorde aucune approbation ni fusion de PR distante. B26 reste partiel tant que B33/has_lab/Q16 ne sont pas raccordés ; B27 ne rend pas ARB05 active. Les lots B20, BC01 et B33 sont préparés indépendamment, puis seront composés et testés dans l'ordre. Aucun BACKEND_GATE, GO_FRONTEND, GO_PRODUCTION ou déploiement.

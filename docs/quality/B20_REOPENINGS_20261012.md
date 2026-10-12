# B20 — Réouverture et historique, 12 octobre 2026

## Périmètre et provenance

Branche isolée `backend/communaute-entraide-b20-reouverture` depuis B19 `39f96e94d8664bb39da4083a57fbd93ba295a643` (#38). Préparation autorisée pendant la revue, identité Git existante et auteur humain distincts de l'assistance Codex. Aucun avis humain ni fusion distante.

POST /requests/{id}/reopen et GET /requests/{id}/resolutions : propriétaire seul, version stricte, idempotence, révocation datée de la résolution et journal immuable ; proposition acceptée et données historiques conservées. Notifications privées après commit. Audit sans motif, note ou code. Droits et cohérence filtrés avant total/pagination. [Contrat](../api/REOPENINGS.md).

## Réception locale du code figé

PHP8.5.10, PostgreSQL17, base dédiée exclusive `haas_b20_review_test`, 127.0.0.1:55447 ; runner `node .worktrees/run-pr-review-20261011.cjs b20`. Vendor indépendant au composer.lock conservé.

| Commande après le runner | Résultat reçu |
| --- | --- |
| `vendor/bin/phpunit --testsuite Unit,Feature,Architecture --colors=never --log-junit storage/logs/b20-unit-architecture-final-v2.xml` | **542 tests /5375 assertions**, erreurs/échecs/skips0 |
| SQL ciblé : RequestReopeningsTest, RequestReopeningHistoryRegressionTest, RequestReopeningsConcurrencyTest, RequestReopeningSchemaTest, RequestReopeningFreshMigrationTest, RequestReopeningNotificationMigrationTest, RequestReopeningNotificationsTest, HelpRequestsMigrationTest, IdentityMigrationTest | **94 /1097**, erreurs/échecs/skips0 ; sept courses entre vrais processus incluses |
| `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Niveau8, aucune erreur |
| `vendor/bin/pint --dirty --test` | Réussi |
| `../scripts/generate-api-types.php --check` | **71 types** |
| `vendor/bin/phpunit --testsuite Integration --colors=never --log-junit storage/logs/b20-integration-final.xml` | En cours au bilan, **aucun résultat complet reçu** |

Total disjoint reçu **636 tests /6472 assertions** ; les 94 ciblés ne s'additionnent pas à une future globale Integration. Documents18/18 et7/7, diffcheck reçus par le writer ; manifeste final après sélection explicite par l'intégrateur.

## Défauts reproduits et corrigés

Contre-épreuves de revue13/70 : huit échecs réels. Trois objets JSON imbriqués provoquaient500 lors du merge stdClass dans InputBag ; cinq incohérences d'acceptant, parent ou version restaient comptées. Restauration associative avec garde de forme, entier JSON brut et filtres relationnels avant count corrigés : **13/102**, zéro réserve. JSON4.0 demeure un float refusé ; snapshots legacy NULL restent inconnus, sans backfill.

Anciens helpers de migration2/15 avec deux erreurs de downgrade : les enfants B20 n'étaient pas descendus avant leur parent. Protocole de nettoyage de fixtures sur base locale gardée et ordre de migrations corrigés : **2/41**. Les refus de downgrade avec historique réel restent protégés. Un ancien `b20-targeted-final.xml` vide après renouvellement du host est **interrompu**, sans succès inféré ; seuls les JUnit final-v2 complets fondent le bilan.

## État et raccord restant

B20 **IN_PROGRESS** : commandes/historique/notification reçus sur ciblés ; Integration complète, CI exacte et revue humaine à recevoir. **AC11 partiel** : signal externe « Source rouverte — revue nécessaire » à raccorder sur la composition réelle des capsules B22–B27. Aucune version publiée, empreinte ou artefact muté par cette branche. B21 est un lot distinct. Aucun BACKEND_GATE, GO_FRONTEND ou GO_PRODUCTION.

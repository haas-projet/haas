# B26 — Préparation du catalogue public avant revue

## Reprise du 11 octobre 2026

Départ : PR #35, commit `b568a511abf99ef606090816d5e9ad93d32c6335`, parent B25 `ce75045`. La reprise conserve les historiques et le socle main/B38. Les anciens worktrees restent intacts. Cette preuve décrit une réception partielle ; aucune approbation humaine, publication, fusion de PR, gate ou réception globale B26 n'est déclarée.

## Premier correctif : validation et attribution

- La limite de recherche compte les caractères Unicode : 200 caractères acceptés, 201 refusés.
- Les paramètres `q` et `sort` sous forme de tableau sont refusés avec 422 avant tout cast ; les erreurs de validation initiales restent prioritaires.
- L'attribution expose le pseudonyme public `handle`, jamais l'ancien champ privé `name`.

Contrôles réellement exécutés sur PHP 8.5.10 et PostgreSQL 17, base locale dédiée `haas_b26_review_test`, via `node .worktrees/run-pr-review-20261011.cjs b26` depuis la racine :

| Commande PHP passée au runner | Résultat |
| --- | --- |
| `vendor/bin/phpunit --testsuite Unit,Integration --filter 'CapsuleCatalogueFilterDataTest\|test_unicode_search_accepts\|test_array_filters_are\|test_detail_attributes' --colors=never --log-junit storage/logs/b26-input-red.xml` avant correction | 8 tests / 8 assertions, 1 erreur et 6 échecs réels |
| `vendor/bin/phpunit --testsuite Unit,Integration --filter 'CapsuleCatalogueFilterDataTest\|CapsuleCatalogueHttpTest' --colors=never --log-junit storage/logs/b26-input-green.xml` après correction | 23 tests / 78 assertions, réussi |
| `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Niveau 8, aucune erreur |
| `vendor/bin/pint --test` | Réussi |

Les cas rouges sont inclus dans le ciblage vert ; leurs nombres ne s'additionnent pas. Journaux et JUnit restent ignorés. La fixture du nom privé est fictive. La suite complète et la CI du nouveau SHA ne sont pas encore exécutées.

## Périmètre encore ouvert

Le filtre de technologie doit correspondre à la dernière version publiée et l'hydratation doit être bornée à une version par carte. L'enveloppe de détail OpenAPI doit décrire `data`. Ces correctifs seront reçus séparément avant synchronisation du parent B25 final et réception complète.

`has_lab` et Q16 restent dépendants de B33, absent de ce lot. B26 n'est donc pas déclaré DONE. Le catalogue ne donne aucun droit de mutation. Les capsules documentaires publiées restent indépendantes du masquage de leur demande source ; l'API n'expose ni identité, lien, extrait ou identifiant de cette source. Une attribution historique publique n'est pas une inscription à l'annuaire.

Les lectures successives ne constituent pas un snapshot transactionnel global. Chaque nouvelle requête applique la visibilité courante ; aucun retrait ne peut révoquer des octets déjà reçus. Aucun frontend, laboratoire exécutant du code utilisateur ou déploiement n'est ajouté.

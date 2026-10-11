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

## Périmètre restant au premier correctif

Le filtre de technologie doit correspondre à la dernière version publiée et l'hydratation doit être bornée à une version par carte. L'enveloppe de détail OpenAPI doit décrire `data`. Ces correctifs seront reçus séparément avant synchronisation du parent B25 final et réception complète.

`has_lab` et Q16 restent dépendants de B33, absent de ce lot. B26 n'est donc pas déclaré DONE. Le catalogue ne donne aucun droit de mutation. Les capsules documentaires publiées restent indépendantes du masquage de leur demande source ; la projection de provenance ne charge ni n'expose l'identité, un lien, un extrait ou l'identifiant de cette source. Une attribution historique publique n'est pas une inscription à l'annuaire. Le corps documentaire approuvé reste celui de sa version ; s'il contient lui-même un secret ou un contenu à retirer, la capsule doit être masquée/revue selon la procédure de modération.

Les lectures successives ne constituent pas un snapshot transactionnel global. Chaque nouvelle requête applique la visibilité courante ; aucun retrait ne peut révoquer des octets déjà reçus. Aucun frontend, laboratoire exécutant du code utilisateur ou déploiement n'est ajouté.

## Deuxième correctif : sélection, chargement et contrat

- Une sélection PostgreSQL `DISTINCT ON (capsule_id)` choisit la dernière version actuellement publiée, par `published_at` décroissant puis `id` croissant. Le filtre de technologie, le tri et la carte utilisent ce même ID avant pagination.
- Le chargement des cartes est limité aux IDs de la page (20 par défaut, 50 maximum), une version au plus par carte, avec technologies mais sans corps/limites des versions. L'identifiant technique choisi ne figure pas dans la Resource.
- Le détail charge le corps de la seule version choisie ; l'historique complet contractuel ne charge que ses métadonnées. Il ne charge pas le propriétaire et ne sélectionne que `id, handle` pour les contributeurs.
- Les deux réponses de détail OpenAPI décrivent l'enveloppe `data`. Les bornes/defaults de pagination concordent avec `PageData` et les types API sont régénérés (66 types, sans application frontend).

Régressions avant ces corrections : `b26-selection-red.xml`, 3 tests / 6 assertions, 3 échecs réels (ancienne technologie, 15 versions hydratées pour une carte, enveloppe absente) ; `b26-history-red.xml`, 1 test / 4 assertions, 1 échec réel (4 corps chargés pour un seul détail). Une première relance a échoué avec 30 tests / 2548 assertions et une erreur dans la nouvelle fixture OpenAPI : le paramètre du chemin était écrit `versionLabel` au lieu de `version`. Ce chemin de test a été corrigé. Aucun de ces résultats n'est présenté comme réussi.

Le ciblage suivant `CapsuleCatalogueFilterDataTest|CapsuleCatalogueHttpTest|OpenApiContractTest|GeneratedApiTypesTest` a ensuite réussi : **37 tests / 2610 assertions**, JUnit `b26-catalogue-green.xml`. Il inclut les régressions et des contrôles de visibilité avant compteurs/pagination, lectures répétées sans mutation documentaire, tri déterministe à dates égales, limites et paramètres tableaux, labels appartenant à une autre capsule, caractères `%`, `_`, `!` et texte SQL traités littéralement, pseudonyme public et source masquée indépendante sans donnée privée de source ou de compte projetée. Les fixtures de publication directe reçoivent la lecture B26 ; elles ne valident pas les prérequis du Service de publication B25.

La lecture indépendante de `pr_capsule_schema` n'a trouvé aucun autre défaut concret dans la sélection, le filtre et l'hydratation ; elle n'est pas comptée comme un test. Un retrait entre les requêtes de pagination et d'hydratation peut produire une carte sans version, sans projeter le contenu de la version retirée. L'historique de labels n'est pas tronqué ; ce lot borne les modèles/corps chargés pour les cartes et les corps du détail, pas le nombre historique de publications.

PHPStan a d'abord refusé un opérateur nullsafe inutile dans une nouvelle assertion de fixture ; l'opérateur a été corrigé sans ignore/baseline. Contrôles reçus avant la dernière relecture de nullabilité, sur le même runner et la même base dédiée :

| Commande | Résultat réellement reçu |
| --- | --- |
| `vendor/bin/phpunit --testsuite Unit,Feature,Integration --filter 'CapsuleCatalogueFilterDataTest\|CapsuleCatalogueHttpTest\|OpenApiContractTest\|GeneratedApiTypesTest' --colors=never --log-junit storage/logs/b26-catalogue-final-targeted.xml` | 37 tests / 2610 assertions, réussi |
| `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Niveau 8, aucune erreur |
| `vendor/bin/pint --test` | Réussi |
| `../scripts/generate-api-types.php` | 66 types générés ; vérification incluse dans le ciblage |
| `node scripts/validate-pack.mjs` depuis le worktree | 18 contrôles documentaires réussis, aucun test applicatif |
| `node scripts/check-deployment-docs.mjs` depuis le worktree | 7 contrôles documentaires réussis, aucun serveur testé |

Les ciblages et leurs relances se recouvrent : ne pas additionner leurs nombres. La réception complète attend la synchronisation du parent B25 final ; la CI du nouveau SHA et la revue humaine demeurent à recevoir. Le premier correctif local est `a56f0144fb104d07c52e51a5aa7c9019dc2f0e94` ; le SHA du présent correctif sera communiqué après son commit, sans auto-référence dans les bytes de ce commit.

## Dernière relecture : valeurs nulles du contrat B26

Les schémas du catalogue utilisaient encore `nullable: true` et le générateur produisait des types non nullables pour `version`, `editorial_origin` et `limits`. Deux probes Feature ont échoué réellement : **2 tests / 3 assertions, 2 échecs**, JUnit `b26-nullable-red.xml`. Le bloc des schémas Catalogue/PaginationMeta décrit maintenant les unions `[T, 'null']`, conformément aux [types JSON Schema](https://json-schema.org/understanding-json-schema/reference/type). Le générateur et les schémas des autres lots sont inchangés ; 66 types API sont régénérés et vérifiés, avec leurs valeurs nulles.

Contrôles finaux avant transmission du code au parent, toujours via le runner B26 :

- `vendor/bin/phpunit --testsuite Unit,Feature,Integration --filter 'CapsuleCatalogueFilterDataTest|CapsuleCatalogueHttpTest|OpenApiContractTest|GeneratedApiTypesTest' --colors=never --log-junit storage/logs/b26-final-before-parent.xml` : **39 tests / 2620 assertions, réussi**.
- `vendor/bin/phpstan analyse --memory-limit=512M --no-progress` : niveau 8, aucune erreur.
- `vendor/bin/pint --test` : réussi.

Ce ciblage final remplace les nombres des relances ciblées précédentes, sans les additionner. Aucun SQL global ni réception complète n'est exécuté avant synchronisation du parent B25 final. PR #35 reste en brouillon et B26 reste partiel : badge/filtre laboratoire, `has_lab` et Q16 dépendant de B33 ne sont pas livrés. La CI exacte et la revue humaine demeurent à recevoir.

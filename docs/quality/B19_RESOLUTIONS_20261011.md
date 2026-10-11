# B19 — Accepter une proposition — 11 octobre 2026

## État et périmètre

Branche isolée `backend/communaute-entraide-b19-resolution`, issue du head B18 `d4db146786a925e9d08004c35b296f6950d0dc83` de la [PR #37](https://github.com/haas-projet/haas/pull/37). Main reste `d0d47c5dd62887fcf18361f499a33a800538b2b1`. **B19 IN_PROGRESS jusqu’à réception CI publiée**, puis revue humaine et intégration des prérequis. Publication distincte à la demande de continuation ; base temporaire B18 afin de rendre le seul diff B19 révisable. Aucune approbation ou signature de Lamine inventée. Le changement préexistant des routes capsules dans le dossier principal est préservé et exclu.

- POST /api/v1/requests/{id}/resolve et GET /api/v1/requests/{id}/resolution, [contrat RESOLUTIONS.md](../api/RESOLUTIONS.md), OpenAPI et 66 types générés. Cinq champs stricts ; JSON brut contrôlé avant trim Unicode, note utile 20–4 000 caractères, contexte 1–255, versions entières et UUID. Sources Markdown inertes ; aucun code, shell ou URL utilisateur exécuté.
- Auteur actif/vérifié seul décide, même face à un admin tiers. Proposition de la même demande, visible et proposée, deux versions actuelles. Comptes par UUID trié, puis parent et proposition verrouillés ; règles relues après attente et avant rejeu. Parent ouvert/en cours vers résolu, version parent +1 ; version et contenu de proposition inchangés. Les autres propositions restent proposées et ne sont plus éditables sur un parent résolu.
- Une seule résolution active ; snapshot de la version choisie, contexte/note déclarés et date serveur. Auto-résolution admise et étiquetée, sans notification à soi. Ce contexte n’est pas une vérification indépendante ni un rapport de laboratoire.
- Résolution, états, deux audits minimaux, outbox et idempotence atomiques. Audit du parent et de la résolution distinct des révisions de contenu B18 ; aucun contexte, note, code ou contact dans audit/outbox/intention. Cinq pannes de stockage forcées vérifient le rollback complet.
- Rejeu exact sans effets supplémentaires et règles actuelles conservées ; autre intention ou version périmée = 409. Résolution révoquée/absente = 404 ; remplacement actif cohérent = 409. GET filtre avant exposition : parent résolu, proposition acceptée, acceptant toujours auteur du parent, version connue identique. Anciennes versions/contextes NULL préservés sans backfill ni histoire inventée.
- Notification resolution.accepted à l’auteur actuel de proposition, livraison séparée après commit et déduplication. Destinataire et visibilité avant total, liste, compteur et marquage. Message générique et chemin interne, sans contenu privé. Migration compose les CHECK présents, conserve les événements B18/futurs et refuse le downgrade avec des données B19.

## Défauts et corrections observés

- Rejeu d’une résolution remplacée : reproduction HTTP valide **1 test / 6 assertions / 1 échec**, 500 au lieu de 409. Distinction du résultat périmé et du stockage corrompu corrigée ; test conservé puis **1 / 12 réussi** sans effets supplémentaires. Une première fixture non vérifiée échouait à 404 avant l’acceptation ; elle ne prouvait pas ce défaut et a été corrigée avant reproduction.
- Première réception HTTP/notifications/migrations : **128 / 1998 / 1 échec**, provider proposal_proposed. Le modèle original gardait son état Eloquent proposé après une acceptation par une autre instance : save ne modifiait donc pas la base. Relecture des modèles et assertions explicites accepted/resolved/versions ajoutées ; provider réellement muté puis **1 / 23 réussi**. Attente de visibilité conservée, aucun filtre assoupli.
- PHPStan a signalé des types nullables/union de modèles, le PHPDoc placé après l’attribut et une date mutable sur une propriété immuable. Gardes de type et fixtures corrigées sans baseline. Pint a signalé imports/espacement ; contrôles finaux reçus.
- Relecture automatisée indépendante du service, des queries et du contrat terminée sans nouvel écart concret ; elle ne constitue pas une revue humaine.

## Réception locale réelle

PHP 8.5.10 ; PostgreSQL 17 dédié `127.0.0.1:55447`, application `haas_b19_review_test`. Runner ignoré vérifiant environnement testing, hôte, port et nom exact. Base fictive B2 `haas_demo_b19_review_test`, rôle restreint séparé ; permission APP/DEMO vérifiée f|t. Cluster propre au travail de revue, sans toucher Laragon 5432 ou une production.

Commandes depuis la racine :

```text
node .worktrees/run-pr-review-20261011.cjs b19 vendor/bin/phpunit --testsuite Unit,Feature,Architecture --colors=never --log-junit storage/logs/b19-nonsql-final-20261011.xml
node .worktrees/run-pr-review-20261011.cjs b19 vendor/bin/phpunit --testsuite Integration --filter 'Resolution|Proposal|CommentsTest|CommentNotificationsTest|HelpRequestsMigrationTest|IdentityMigrationTest' --colors=never --log-junit storage/logs/b19-reception-final-20261011.xml
node .worktrees/run-pr-review-20261011.cjs b19 vendor/bin/phpstan analyse --memory-limit=512M --no-progress
node .worktrees/run-pr-review-20261011.cjs b19 vendor/bin/pint --test
node .worktrees/run-pr-review-20261011.cjs b19 ../scripts/generate-api-types.php --check
node .worktrees/run-pr-review-20261011.cjs b19 C:/ProgramData/ComposerSetup/bin/composer.phar validate --strict --no-check-publish
node .worktrees/run-pr-review-20261011.cjs b19 C:/ProgramData/ComposerSetup/bin/composer.phar check-platform-reqs
node .worktrees/run-pr-review-20261011.cjs b19 C:/ProgramData/ComposerSetup/bin/composer.phar audit --locked --no-interaction
```

| Contrôle | Résultat réel |
|---|---|
| Unit + Feature + Architecture complètes | **523 tests / 5107 assertions**, zéro erreur/échec/skip |
| PostgreSQL ciblé B19 + régressions B18/B17/migrations | **308 tests / 4308 assertions**, zéro erreur/échec/skip |
| Total disjoint final | **831 tests / 9415 assertions** |
| Concurrence B19 à deux processus | 9 scénarios, inclus dans les 308 SQL ; deux vrais processus PHP et attentes de verrous PostgreSQL observées, sans ajout au total |
| PHPStan niveau 8 / Pint | Réussis, aucune baseline ajoutée |
| Types API | 66 à jour |
| Composer validate / platform / audit verrouillé | Réussis, aucune alerte de sécurité ; dépréciations internes du PHAR installé sous PHP 8.5 observées |
| Documentation | validate-pack.mjs 18/18 ; check-deployment-docs.mjs 7/7, aucun serveur testé |

Suite Integration complète locale **non exécutée** ; les tests ciblés ne la remplacent pas. CI PHP 8.4/8.5 complète du SHA publié à recevoir avant ready. Empreintes et diff sélectionné contrôlés avant commit ; référence réelle du commit dans le bilan/PR, pas dans son propre contenu.

## Limites et prochaine étape

AC09 reçu côté API, affichage/navigateur non exécuté. AC10 vérifié sous concurrence PostgreSQL réelle ; réception CI publiée et revue humaine encore requises. B18 n’est pas intégré. B20 réouverture, B21 non-sélection/archivage et profils de contribution B10 ne sont pas livrés par ce lot. B26 reste partiel sans B33 ; #35/#36 restent Draft. Aucun BACKEND_GATE, GO_FRONTEND ou GO_PRODUCTION, Qodana ou déploiement reçu.

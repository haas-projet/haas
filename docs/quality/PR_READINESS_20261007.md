# Préparation des PR avant approbation — 7 octobre 2026

## Main effectivement intégré

- #31 : fusion `e3bd34c25e45197adb6b74a892601097ef3f8097`, le 7 octobre à 20:02:57 UTC. README et bilan sont conservés.
- #24 : fusion `b76612d1b6587119127fd364244f5248f05f1ff2`, le 7 octobre à 20:24:58 UTC ; head testé `2addb4d82b0c4a8671dd9d1f7e440756d3c5f3b1`. Réponse GitHub `reviews: []` : fusion constatée, aucune revue humaine inventée.
- [CI du head #24](https://github.com/haas-projet/haas/actions/runs/37681430317) et [CI de main](https://github.com/haas-projet/haas/actions/runs/37681824151) : PHP 8.4, PHP 8.5 et backend-ci réussis. Par version : 328 tests / 3563 assertions hors SQL, 343 / 3242 PostgreSQL, total 671 / 6805. Les arbres backend du head et de main sont identiques selon `git diff --exit-code 2addb4d b76612d -- backend`.

B14–B17 DONE dans le catalogue du dépôt, avec leurs preuves antérieures conservées. 17 lots backend entiers sont intégrés. Aucun changement des cartes Systalink n’est annoncé.

## PR restantes et dépendances

| PR | Périmètre | Base de revue | Vérification attendue avant approbation |
|---|---|---|---|
| #29 | B22 : schéma, attribution et versions | main | Retrait sans réécriture, immutabilité SQL, champs serveur protégés, migrations et rollback. |
| #30 | B23 : brouillons documentés | branche B22 de #29 | Source résolue cohérente, droits/version relus, champs bornés, conflits et concurrence réelle. |
| #33 | B24 : revue indépendante | branche B23 de #30 | Idempotence/version, corrections et resoumission, audit atomique, historique conservé et notification après commit au propriétaire de la capsule. Publication B25 future. |
| #28 | B38 : API B2 fictive | main | Runtime/origines/clés/DB séparés, aucun cookie HAAS, quotas/TTL, refus réel de connexion métier et concurrence. |

Préparation parallèle dans quatre worktrees distincts, chaque suite SQL sur sa base locale dédiée à127.0.0.1:55447 ; rôle B2 dédié sans privilèges ni CONNECT vers la base métier de revue. Service PostgreSQL existant à5432 préservé. Les résultats finaux de chaque lot et incidents intermédiaires sont consignés dans ses preuves et dans les checks du head exact. Un ancien check vert ne valide pas une correction plus récente.

Ordre d’approbation des capsules : #29, puis #30, puis #33 ; synchronisation et CI après toute fusion de prérequis. #28 est indépendante. Aucune fusion des quatre PR n’est effectuée par cette préparation ; aucune approbation simulée.

## Contrôles locaux de préparation

- B22 : correctif `214832250b79bcac9d2f622f0b038a7074a6471a` testé à 551 tests / 4157 assertions avant le dernier main. Après intégration de main b76612d : **335 / 3595 hors SQL et 399 / 3390 PostgreSQL**, soit **734 tests / 6985 assertions**, Pint et PHPStan niveau 8 réussis. Pack 18/18, déploiement 7/7 et 49 types API vérifiés. Preuve complète : B22_CAPSULE_SCHEMA.md.
- B23 : correctif `57f0727bdc0e67c87a05f191cebc42f3dd4f8b40`, contrôles ciblés 13 / 111 et six courses réelles compris ; les suites combinées avec B22 renforcé et main sont ensuite exécutées avant publication. Preuve : B23_DRAFT_READINESS.md sur sa branche.
- B24 : correctif `06d0fc566649473c6716a5e9c916affd85554262`, contrôles ciblés 33 / 162 et complémentaires 23 / 90. Le raccord de notification Q12 requis par le cahier :355 et les suites avec les prérequis corrigés restent à terminer. Preuve : B24_REVIEW_READINESS.md sur sa branche.
- B38 : correctif `22bbf93d4b98beaa4f7af591ca1fe95f9e56575c`, contrôles d’isolation/concurrence 64 / 348 réussis ; synchronisation avec main et suites complètes avant publication. Preuve : B38_ISOLATION_20261007.md sur sa branche.

Ces étapes locales ne sont pas des approbations ni la CI d’un head publié ultérieurement. Consulter les checks de chaque PR pour le commit exact et la preuve finale.

## État des branches

Le 7 octobre à 20:36 UTC, vérification fraîche de dix références et de l’absence de PR ouverte dépendante, puis push atomique : les trois branches permanentes sont avancées de `f1f6238` à main `b76612d` ; les seules références `backend/communaute-entraide-b14` (head `2addb4d`) et `review/maadinaa-20261007` (head `0575552`) sont supprimées. Leurs commits sont tous ancêtres de main et les branches/worktrees locaux sont conservés. Inventaire après opération : **huit références**, `main` et trois branches permanentes, plus quatre temporaires nécessaires à #28/#29/#30/#33.

## Limites

B35 reste partiel. Aucun BACKEND_GATE, GO_FRONTEND, GO_PRODUCTION, Qodana reçu ou déploiement. Aucun code utilisateur exécuté. Le fichier de routes modifié préexistant dans le répertoire principal est préservé. Les branches temporaires restent nécessaires tant que leurs PR ne sont pas intégrées.

## État final de préparation — 7 octobre 2026, 22:06 UTC

Constats de publication, statut des PR et logs CI transmis par l’intégrateur ; aucune approbation humaine enregistrée par cette préparation. Les étapes ci-dessus restent historiques.

| PR | Head contrôlé | Réception locale unique | CI du head exact | État |
|---|---|---|---|---|
| #29 — B22 | `65bc08a0f0ed30eb9149cef8b74a9f7a14641660` | 734 tests / 6985 assertions | [37684871015](https://github.com/haas-projet/haas/actions/runs/37684871015), PHP 8.4/8.5 : 734 / 6985 chacune | Prête à revue, sans approbation |
| #30 — B23 | `11b9fec088cb1ecd11e074f1b8a3fe3a0805ef85` | 821 / 7454 | [37692706328](https://github.com/haas-projet/haas/actions/runs/37692706328), PHP 8.4/8.5 : 821 / 7454 chacune | Prête à revue, sans approbation |
| #28 — B38 | `0ad5a992d108b7d840fe19fca99da3a78366012c` | 770 / 7288 | [37690142490](https://github.com/haas-projet/haas/actions/runs/37690142490), PHP 8.4/8.5 : 770 / 7291 chacune | Prête à revue, indépendante des capsules |
| #33 — B24 | `e01202fb4f89381c44d4ba5ed2a3fcf70cac47f3` | 350 / 3800 hors SQL + 524 / 4037 SQL = **874 / 7837** | [37693092990](https://github.com/haas-projet/haas/actions/runs/37693092990), PHP 8.4/8.5 : 874 / 7837 chacune | Code prêt localement ; publication de ce bilan et CI de son futur SHA avant ready |

La suite PostgreSQL B24 complète est réellement terminée avec exit 0 et JUnit sans erreur, échec ou test ignoré. Le code `994de150` est identique à `e01202f` dans `backend/` ; les preuves B23 entrantes n’ont pas interrompu sa réception. Q12 corrections demandées, droits/visibilité courants, intention outbox transactionnelle, livraison après commit et historique de revue sont contrôlés ; aucune acceptation/publication B25 n’est ajoutée. Détails : [B24_REVIEW_READINESS.md](B24_REVIEW_READINESS.md).

Main reste `b76612d1b6587119127fd364244f5248f05f1ff2`. Inventaire distant transmis par l’intégrateur : **huit références**, soit `main` et trois branches de domaine permanentes, plus quatre branches des PR restantes. Ordre de revue/fusion des capsules : **#29 → #30 → #33**, avec synchronisation et nouvelle CI après fusion d’un prérequis ; **#28 indépendante**. Ce bilan ne fusionne aucune de ces PR et ne change aucun gate ou statut DONE. Le SHA de son propre futur commit sera donné dans le bilan de session après création, puis sa CI observée séparément.

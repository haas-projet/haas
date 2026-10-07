# B24 — Préparation de la revue indépendante

## Périmètre et statut

Audit et corrections Codex du 7 octobre 2026 sur la PR #33, source `e5dabac`, parent B23 `93fafea`. Première étape locale avant intégration du B23/B22/main corrigé. Aucune approbation humaine attribuée. L'indépendance existante du reviewer est conservée : moderator/admin actif vérifié, distinct du propriétaire et de tous les contributeurs de cette version, même administrateur.

B24 soumet et demande des corrections ; il ne livre ni décision d'acceptation ni publication. B25 doit appliquer sa revue indépendante et ses règles d'auteur avant publication. Le cycle `draft → in_review → changes_requested → édition → in_review` conserve toutes les notes et attributions antérieures. Le reset du reviewer courant à la resoumission n'efface aucune décision historique.

## Corrections

| Invariant | Implémentation et preuve |
|---|---|
| Intention et version | Deux FormRequests stricts, DTO borné, UUID v4 `Idempotency-Key` et `lock_version` obligatoires ; champ inconnu = 422, verrou périmé = 409. |
| Droits actuels | IdempotencyService relit l'acteur ; verrous acteur, capsule puis version, Policy sur l'état courant. Suspension, révocation de rôle et ajout de contribution après attente sont recontrôlés. |
| Rejeu | Même clé/charge = même résultat, une décision et un audit ; charge ou état/version modifié = 409, rôle devenu insuffisant = 403. |
| Notes | Unicode 20–2000, retours à la ligne autorisés, contrôles interdits et filtre indicatif de secrets. Ce filtre n'atteste pas l'absence de tout secret. JSON textuel, aucune exécution du contenu. |
| Attribution | `reviewed_lock_version` capture la version relue avant incrément. Journal append-only protégé contre UPDATE/DELETE ; la FK refuse la suppression de la version et conserve le reviewer. Anciennes décisions : snapshot null, sans révision inventée. |
| Atomicité | Vraie panne SQL simulée par trigger sur l'audit, sur soumission et correction : état, reviewer, revue et intention idempotente annulés. L'ancien test qui ne faisait qu'un appel interdit est renommé sans le présenter comme une panne d'audit. |
| Concurrence | Six courses avec deux processus PHP, barrière et deux attentes PostgreSQL effectivement vérifiées : submit/review avec clé identique ou distincte, rôle révoqué et admin devenu contributeur. |

Les réponses de mutation sont privées et `no-store`. Les modèles n'acceptent pas les métadonnées serveur en mass assignment ; les services les fixent explicitement.

## Preuves locales observées

PHP 8.5.10, PostgreSQL 17, base dédiée `haas_b24_review_test` sur `127.0.0.1:55447`, utilisateur `haas_test`. Vendor indépendant au lock identique ; clé locale ignorée, jamais publiée.

| Commande | Résultat |
|---|---|
| `php vendor/bin/phpunit tests/Integration/Capsules/CapsuleReviewHttpTest.php tests/Integration/Capsules/CapsuleReviewReadinessTest.php` (premier correctif) | 21 tests / 81 assertions, OK |
| `php vendor/bin/phpunit tests/Integration/Capsules/CapsuleReviewHttpTest.php tests/Integration/Capsules/CapsuleReviewReadinessTest.php tests/Integration/Capsules/CapsuleReviewConcurrencyTest.php tests/Integration/Capsules/CapsuleVersionReviewsSchemaTest.php` | 33 tests / 162 assertions, OK |
| `php vendor/bin/phpunit tests/Integration/Capsules/CapsuleReviewReadinessTest.php tests/Integration/Capsules/CapsuleVersionReviewsSchemaTest.php tests/Unit/Capsules/ReviewCommandDataTest.php` (compléments rollback et upgrade) | 23 tests / 90 assertions, OK |
| `php vendor/bin/phpstan analyse --no-progress` (après les compléments de tests) | `[OK] No errors` |
| `php vendor/bin/pint --test` | `passed` |
| `node scripts/validate-pack.mjs` / `node scripts/check-deployment-docs.mjs` | 18 + 7 contrôles documentaires OK, aucun serveur testé |
| `php scripts/generate-api-types.php --check` | 36 types API à jour |

L'upgrade/down/up de la migration additive conserve ID, note, version et reviewer d'une décision antérieure ; aucun rollback de production testé. Les premiers problèmes de configuration de test locaux rencontrés en B23 ne constituent pas des succès B24.

## Travail restant après le premier correctif

1. Intégrer le parent B23/B22/main corrigé, préserver ses gardes de visibilité, adapter les fixtures publiées aux contraintes B22 et relancer les suites combinées.
2. Fermer Q12 : le cahier §15 exige la notification à l'auteur du brouillon pour publication **ou corrections demandées**. Raccordement à l'outbox commune main, dans la transaction de revue, puis livraison distincte après commit ; message/références seuls et visibilité courante avant liste/compteur/marquage. Cette notification n'est pas encore livrée par ce premier commit.
3. CI distante, Qodana et revue humaine du commit final : non exécutés ici. Aucun `DONE`, frontend, GO_FRONTEND ou déploiement.

L'intégrateur consigne les SHA après création des commits ; aucun SHA ne prétend se référencer dans son propre contenu.

## Candidat après synchronisation et raccordement Q12

Parent combiné `81ac242c1cb9e442aa2809715ebe24b2fc18ca98`, incluant le correctif B23 `d7ef6eb` et son schéma B22/main. Les gardes de visibilité B23 sont préservées. La fixture publiée de soumission ajoute date et reviewer fictif vérifié, modérateur et distinct du propriétaire ; son refus 403 est réellement contrôlé.

Probes de validation avant correction, sur `08d89e3` : **3 tests / 13 assertions, 2 échecs**. Les deux actions de revue donnent TypeError 500 pour `lock_version="1"` ; elles exigent maintenant un entier JSON strict et rendent 422. La note utile `x` complétée par dix-neuf espaces est déjà rejetée 422 par le middleware, sans défaut supplémentaire de note observé.

Q12 est raccordée dans ce lot pour **les corrections demandées seulement** : événement stable UUID du journal, destinataire propriétaire de la capsule relue sous verrou, intention écrite avec revue/transition/audit/idempotence, livraison séparée après commit. La notification ne copie ni note, corps, adresse ni titre. La Query filtre propriétaire actuel actif et vérifié et capsule visible avant liste, compteur, pagination et marquage. Les tests contrôlent observateur SQL indépendant avant commit, rollback, rejeu et livraison dédupliqués, panne réelle d’enregistrement/livraison, visibilité retirée, transfert et perte de vérification. Le cycle correction/édition/resoumission conserve l’attribution historique ; aucune acceptation ou publication B25 n’est ajoutée.

La revue parallèle identifie aussi une perte de snapshot dans le down du journal : une régression propre exécutée avant fix échoue **1 test / 1 assertion** car aucun refus n’est levé. Le down prend désormais un verrou de table exclusif dans la transaction avant contrôle et DDL ; tout snapshot connu entraîne un refus sans perte de colonne, trigger ou attribution. L’additive des types notifications refuse de perdre ses événements stables, sous verrous outbox puis boîte interne. Les helpers Tests gardés par `TestDatabaseGuard` déposent seulement leurs fixtures avant le rollback automatique du schéma de test ; cette remise à zéro ne simule pas un downgrade de production. La première suite Q12 ciblée est interrompue pour ce correctif de journal, sans succès global revendiqué.

| Contrôle du candidat | Résultat réel |
|---|---|
| Ciblés revue HTTP/readiness/concurrence/journal et notifications B17/Q12 | 53 tests / 496 assertions, OK |
| Onze ciblés exacts après les dernières assertions (Q12, snapshots, integer, note et fixture publiée) | 11 tests / 116 assertions, OK ; ils recouvrent les précédents |
| `php vendor/bin/phpunit --testsuite Unit,Feature,Architecture` | 350 tests / 3800 assertions, OK |
| Pint / PHPStan niveau 8 | `passed` / `[OK] No errors` |
| `composer validate --strict --no-check-publish` | composer.json valide ; notices de dépréciation PHP 8.5 du PHAR externe, aucun échec |
| Pack / documentation déploiement / types API | 18/18, 7/7, 57 types à jour |
| `php vendor/bin/phpunit --testsuite Integration --log-junit storage/logs/b24-q12-integration.xml` | Suite complète lancée sur `haas_b24_review_test`, résultat encore en attente |

Journal SQL ignoré `backend/storage/logs/b24-q12-integration.log`. Aucune somme de ciblés qui se recouvrent n’est annoncée comme nombre de tests uniques. Le candidat est destiné à la CI en brouillon ; résultat SQL complet, CI du SHA exact, Qodana et revue humaine restent à recevoir. Aucun `DONE`, gate, frontend ni déploiement.

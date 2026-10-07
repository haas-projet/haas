# B23 — Préparation des brouillons pour revue

## Périmètre et statut

Revue technique Codex du 7 octobre 2026, à partir de la PR #30 au commit source `93fafea`. Q8 (PATCH) et Q9 (auteur de la proposition acceptée) étaient déjà fermées ; leur travail est conservé. Cette préparation ne constitue ni une approbation humaine, ni B25, ni un BACKEND_GATE. Les protections B22 actualisées et le nouveau main doivent encore être intégrés et contrôlés ensemble avant publication du correctif.

## Défauts reproduits puis corrigés

Le premier probe ciblé, après les ajouts de validation mais avant les corrections de services, a produit 7 tests / 41 assertions avec 4 échecs : résolution active sur demande encore draft acceptée (201), collision de slug rendue en 500, `limits: null` conservant la valeur, rejeu après édition rendu en 201. Ce probe ne prétend pas avoir testé le commit distant inchangé.

| Comportement | Correction et preuve |
|---|---|
| Provenance réelle | Demande verrouillée `resolved`, résolution active, proposition acceptée appartenant à cette demande et attribution cohérente. Q9 conservée, droits relus après les verrous. |
| Conflits | Collision de slug sérialisée par verrou PostgreSQL et rendue en 409 ; doublon de libellé de version rendu en 409 sans écriture partielle. Le précédent test qui interceptait tout `Throwable`, y compris son échec, est remplacé par une assertion HTTP réelle. |
| Validation | Champs inconnus refusés, technologies connues et distinctes, liste stricte, tailles Unicode cohérentes, contrôle indicatif de secrets et caractères de contrôle. Ce filtre ne garantit pas la détection de tout secret. |
| Edition | Présence de `limits` distinguée de sa valeur : null efface et absence conserve. Acteur actuel, capsule puis version verrouillés ; droits et `lock_version` contrôlés avant mutation. |
| Rejeu | Résultat attaché à la version exacte et son verrou, relu avec les droits actuels ; version modifiée = 409, propriétaire remplacé = 403. Projection chargée dans la transaction et réponse privée sans stockage en cache. |
| Courses réelles | Deux processus PHP avec barrière d’entrée, deux attentes de verrous vérifiées dans `pg_stat_activity` : clé identique, collision de slug, édition simultanée, suspension de l’acteur, transfert de propriétaire, réouverture de source. |

Les payloads serveur passent par `forceFill` explicite pour préserver les restrictions de modèles B22. Le PHPDoc de `HelpRequest.state` décrit son cast enum existant ; la Policy qui consulte SQL indique son impureté réelle sans désactiver PHPStan.

## Environnement et contrôles observés

PHP 8.5.10, PostgreSQL 17, base dédiée `haas_b23_review_test` sur `127.0.0.1:55447`, utilisateur de tests `haas_test`. Vendor copié indépendamment, même lock Composer. APP_KEY de test ignorée et jamais consignée.

| Commande | Résultat |
|---|---|
| `php vendor/bin/phpunit tests/Integration/Capsules/CapsuleDraftReadinessTest.php tests/Integration/Capsules/CapsuleDraftConcurrencyTest.php` | 13 tests / 111 assertions, OK |
| `php vendor/bin/phpunit tests/Integration/Capsules` | 92 tests / 290 assertions, OK |
| `php vendor/bin/phpunit --testsuite Unit,Feature,Architecture` | 323 tests / 2675 assertions, OK |
| `php vendor/bin/phpstan analyse --no-progress` | `[OK] No errors` |
| `php vendor/bin/pint --test` | `passed` |
| `node scripts/validate-pack.mjs` | 18 contrôles documentaires, OK ; aucun test applicatif |
| `node scripts/check-deployment-docs.mjs` | 7 contrôles documentaires, OK ; aucun serveur testé |
| `php scripts/generate-api-types.php --check` | 33 types API à jour |

Les premiers lancements avaient une configuration de test CORS/Sanctum incomplète, puis une URL locale doublée lors d’une écriture concurrente du fichier ignoré ; ils ont échoué avant les endpoints. Le rerun ci-dessus fixe explicitement les origines locales sans changer la configuration applicative. Un lancement intermédiaire de la suite capsules avait aussi un échec 422 au login du test Unicode ; les 13 tests ciblés, dont ce test, passent ensuite. La preuve ne transforme pas ces essais en succès.

## Restant avant réception

Intégrer B22 et le main actualisés, adapter les fixtures de publication aux contraintes réelles puis relancer les suites combinées complètes. CI distante du commit corrigé, Qodana et revue humaine : non exécutés ici. Aucun frontend, déploiement, achat ou GO_FRONTEND. L’intégrateur consigne le SHA réel après ce commit, sans essayer de l’inclure dans son propre contenu.

## Complément après synchronisation B22/main

Parent combiné `94aeae9836acf7c8ad3371604d388ef40d5fd3e3` : schéma B22 renforcé et main `b76612d`, suivis B22 repris. La première suite SQL complète a été interrompue sur instruction de l'intégrateur après des cas partiels et un `E` observé : fixture publiée définissant seulement son état, désormais invalide. Aucun résultat global n'est revendiqué pour cet essai.

La fixture de PATCH d'une version publiée ajoute une date et un reviewer fictif vérifié, modérateur et distinct du propriétaire ; le refus HTTP 403 demeure attendu. La Policy refuse aussi `hidden_at` sur la source verrouillée avant création **et au rejeu**. Trois régressions couvrent auteur de source masquée, source devenue masquée après création et service relisant la base avec un ancien objet source. Une septième course réelle masque la source pendant l'attente des acteurs et vérifie deux refus sans brouillon/audit/intention.

Commandes observées après ces corrections : ciblés `CapsuleDraftReadinessTest`, `CapsuleDraftConcurrencyTest`, `CapsuleDraftUpdateHttpTest` = **28 tests / 158 assertions, OK** ; Pint `passed` ; PHPStan `[OK] No errors`. Les 54 types générés du parent sont conservés. La suite SQL complète combinée reste à relancer après ce commit ; l'interruption précédente n'est pas un succès.

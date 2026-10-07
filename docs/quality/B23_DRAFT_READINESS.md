# B23 — Préparation des brouillons pour revue

## Périmètre et statut

Revue technique Codex du 7 octobre 2026, initialement à partir de la PR #30 au commit source `93fafea`. Q8 (PATCH) et Q9 (auteur de la proposition acceptée) étaient déjà fermées ; leur travail est conservé. Les sections initiales gardent les essais et prérequis de cet état. La réception complète du code combiné définitif `d7ef6eb8c750987ddf509eba6647007162c1bec9` figure à la fin de cette preuve. Cette préparation ne constitue ni une approbation humaine, ni B25, ni un BACKEND_GATE.

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

## Restant avant réception — état initial

À ce stade initial : intégrer B22 et le main actualisés, adapter les fixtures de publication aux contraintes réelles puis relancer les suites combinées complètes. CI distante du commit corrigé, Qodana et revue humaine : non exécutés ici. Aucun frontend, déploiement, achat ou GO_FRONTEND. L’intégrateur consigne le SHA réel après ce commit, sans essayer de l’inclure dans son propre contenu.

## Complément après synchronisation B22/main

Parent combiné `94aeae9836acf7c8ad3371604d388ef40d5fd3e3` : schéma B22 renforcé et main `b76612d`, suivis B22 repris. La première suite SQL complète a été interrompue sur instruction de l'intégrateur après des cas partiels et un `E` observé : fixture publiée définissant seulement son état, désormais invalide. Aucun résultat global n'est revendiqué pour cet essai.

La fixture de PATCH d'une version publiée ajoute une date et un reviewer fictif vérifié, modérateur et distinct du propriétaire ; le refus HTTP 403 demeure attendu. La Policy refuse aussi `hidden_at` sur la source verrouillée avant création **et au rejeu**. Trois régressions couvrent auteur de source masquée, source devenue masquée après création et service relisant la base avec un ancien objet source. Une septième course réelle masque la source pendant l'attente des acteurs et vérifie deux refus sans brouillon/audit/intention.

Commandes observées après ces corrections : ciblés `CapsuleDraftReadinessTest`, `CapsuleDraftConcurrencyTest`, `CapsuleDraftUpdateHttpTest` = **28 tests / 158 assertions, OK** ; Pint `passed` ; PHPStan `[OK] No errors`. Les 54 types générés du parent sont conservés. La suite SQL complète combinée reste à relancer après ce commit ; l'interruption précédente n'est pas un succès.

## Dernière revue des entrées et de l’accès à la source

Sur le parent local `1ddf681be493f38590d3d9e224590f869e2070b0`, les deux nouvelles classes `CapsuleDraftInputReadinessTest` et `CapsuleSourceAuthorReadinessTest` sont exécutées **avant** les corrections applicatives : **13 tests / 55 assertions, 10 échecs**. POST versions et PATCH acceptent une longueur brute de vingt caractères puis donnent 500 dans le DTO pour un texte utile trop court ; PATCH accepte le verrou chaîne `"1"` puis donne un TypeError 500. La création nested retire silencieusement les espaces extérieurs du corps valide. Les six scénarios d’auteur source suspendu ou non vérifié autorisent encore création, rejeu et service malgré son ancienne relation chargée. Le premier POST rejette déjà le corps utile trop court car son champ nested était trimmé ; cette ancienne protection doit rester réelle après préservation du format.

Les trois Requests contrôlent désormais le minimum du texte après `trim` sans transformer le corps conservé. L’exception middleware explicite `version.body` préserve son format, comme le champ `body` déjà exclu. `lock_version` est un entier JSON strict : les chaînes numériques sont des erreurs 422. La Policy exige un auteur source actuellement actif et vérifié ; le service le relit avec `FOR SHARE NOWAIT` après le verrou de demande. Seul le conflit de verrou PostgreSQL `55P03` devient un 409 sans écriture, évitant un cycle entre acteurs qui proposent depuis leurs demandes croisées. Le service ne continue jamais après l’échec du verrou.

Trois courses supplémentaires entre deux processus contrôlent suspension de l’auteur tiers, retrait de sa vérification après attente et auteurs croisés. Les deux processus ont réellement obtenu leur verrou d’acteur et attendent sur les demandes avant libération ; au moins un conflit explicite empêche le deadlock. La suite SQL combinée précédente de 461 cas a été interrompue sur instruction de l’intégrateur après environ 448 cas, sans erreur visible à cet instant : **aucun résultat complet n’en est déduit**. La réception complète doit repartir du commit définitif avec la base B23 libre.

Un premier rerun intermédiaire de 44 cas / 260 assertions échoue encore deux fois : PATCH blanc échappe aux règles non implicites de Laravel ; une assertion du test attend à tort le format `errors` alors que HAAS expose `error.fields`. Le Request ajoute `filled` au corps optionnel, et le test utilise le renderer réel. Ces essais ne sont pas présentés comme réussis. Rerun du code définitif : `php vendor/bin/phpunit --filter 'CapsuleDraftInputReadinessTest|CapsuleSourceAuthorReadinessTest|CapsuleDraftConcurrencyTest'` = **24 tests / 186 assertions, OK** ; Pint `passed`, PHPStan niveau 8 `[OK] No errors`, pack 18/18, déploiement 7/7 et **54 types API à jour**. La réception globale est confiée à une autre session sur le worktree et la base libérés ; CI et approbation humaine restent en attente.

## Réception complète du candidat définitif — 7 octobre 2026

Code applicatif reçu propre au commit `d7ef6eb8c750987ddf509eba6647007162c1bec9`, après les correctifs et probes ci-dessus. La réception indépendante relit les droits actuels et le rejeu, l’auteur de source sous `FOR SHARE NOWAIT`, la source résolue et visible, les verrous, les attributions et les validations du corps et de l’entier JSON. Aucun nouveau défaut concret bloquant relevé ; aucun code applicatif, test ou service changé pendant cette réception.

Environnement explicite : PHP 8.5.10, PostgreSQL 17 `127.0.0.1:55447`, base dédiée `haas_b23_review_test`, rôle `haas_test`, `APP_ENV=testing`, `DB_CONNECTION=pgsql`, `DB_PASSWORD` et `DB_URL` vides. `APP_URL=http://localhost:8000`, `FRONTEND_URL` et `CORS_ALLOWED_ORIGINS=http://localhost:5173`, `SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000`. La garde de base reste active ; APP_KEY et journaux locaux sont ignorés.

| Commande réellement exécutée | Résultat |
|---|---|
| `php vendor/bin/phpunit --testsuite Unit,Feature,Architecture --log-junit storage/logs/b23-reception-unit.xml` | **343 tests / 3720 assertions, OK** |
| `php vendor/bin/phpunit --testsuite Integration --log-junit storage/logs/b23-reception-integration.xml` | **478 tests / 3734 assertions, OK** ; 13 min 09,841 s |
| `php vendor/bin/pint --test` | `passed` |
| `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Niveau 8, `[OK] No errors` |
| `php C:/ProgramData/ComposerSetup/bin/composer.phar validate --strict --no-check-publish` | `composer.json` valide |
| `php C:/ProgramData/ComposerSetup/bin/composer.phar check-platform-reqs` | Toutes les exigences satisfaites |
| `php C:/ProgramData/ComposerSetup/bin/composer.phar audit --locked --no-interaction --format=json` | Aucune alerte ni dépendance abandonnée |
| `node scripts/validate-pack.mjs` | 18/18 contrôles documentaires réussis |
| `node scripts/check-deployment-docs.mjs` | 7/7 contrôles documentaires réussis ; aucun serveur testé |
| `php scripts/generate-api-types.php --check` | **54 types API à jour** |

Total des deux suites complètes : **821 tests / 7454 assertions uniques**, zéro erreur, échec ou test ignoré dans les deux XML JUnit. Les 24 / 186 ciblés appartiennent à cette couverture et ne s’ajoutent pas au total ; les essais antérieurs restent historiques. XML locaux ignorés dans `backend/storage/logs/b23-reception-{unit,integration}.xml`. Composer 2.8 émet des dépréciations de son propre PHAR sous PHP 8.5 ; les trois contrôles terminent avec code 0.

Le [run CI du candidat d7ef6eb](https://github.com/haas-projet/haas/actions/runs/37691657985) est entièrement vert, constat transmis par l’intégrateur après lecture des logs : PHP 8.4 et PHP 8.5 réussissent chacun 343 / 3720 hors SQL et 478 / 3734 SQL, soit **821 / 7454 uniques**, comme la réception locale ; le job `backend-ci` réussit également. La présente réception ajoute seulement cette preuve, les suivis participant/IA et les empreintes. La CI du futur commit documentaire doit encore être observée après sa publication par l’intégrateur ; aucune approbation humaine, réception Qodana, statut DONE, BACKEND_GATE ou mise en production n’est déduite de ces contrôles.

Prochaine étape : publier le commit de preuve, lire ses checks exacts, puis présenter la PR #30 à une revue humaine distincte après ses prérequis. B24 doit reprendre ce parent et faire sa propre réception.

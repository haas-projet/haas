# B17 — Preuves des commentaires historisés

7 octobre 2026. Suite de Lamine demandée par l'utilisateur, préparée dans `.worktrees/b17`, branche `backend/communaute-entraide-b17`, depuis B16 `8e9a60e3bc367debdc5c4efe9164084a1b8f0757`. L'implémentation B17 non commitée déjà présente a été conservée, relue et complétée. Le travail local des routes capsules dans le répertoire principal reste exclu.

## Périmètre

Création et édition de ses commentaires, lecture et révisions paginées : cinq opérations HTTP. Session/CSRF, auteur actif vérifié, parent public visible, archive en lecture seule ; aucun bypass administrateur. Idempotence et version recontrôlées sous verrou ; un commentaire ne change ni l'état ni la version de la demande. Contrat détaillé : [COMMENTS.md](../api/COMMENTS.md).

Migration additive des révisions, sans réécrire B11. Les premiers changements d'un commentaire ancien conservent sa version courante ; aucune histoire antérieure n'est inventée. L'audit contient seulement parent/version. L'événement minimal `comment.created` est livré par l'outbox après commit, sans auto-notification ni nouvelle notification lors d'une édition. Les lectures, compteurs et marquages recontrôlent la visibilité du commentaire et de son parent.

Markdown restreint avec `league/commonmark` déjà verrouillé, licence BSD-3-Clause dans `composer.lock` : HTML échappé, code inerte, images remplacées par leur libellé, liens HTTPS sans identifiants, profondeur et délimiteurs bornés. Aucune dépendance ajoutée. Les tests inspectent les éléments et attributs HTML effectivement construits ; un texte échappé contenant `onerror` n'est pas traité comme un attribut actif. Référence : [documentation officielle de sécurité](https://commonmark.thephpleague.com/2.x/security/).

## Contrôles ciblés réellement exécutés

PHP 8.5.10, PostgreSQL 17 local, `127.0.0.1:55447`, base dédiée `haas_b17_test`, rôle `haas_test`. `TestDatabaseGuard` vérifie la cible avant les migrations destructives de test. La configuration privée de test est ignorée par Git ; le service PostgreSQL existant est conservé.

- `php vendor/bin/phpunit tests/Integration/Collaboration/CommentsTest.php tests/Integration/Collaboration/CommentsConcurrencyTest.php tests/Integration/Collaboration/CommentRevisionsMigrationTest.php` : **44 tests / 475 assertions réussis**.
- `php vendor/bin/phpunit tests/Unit/Collaboration/CommentMarkdownTest.php` : **14 tests / 34 assertions réussis**.
- `php vendor/bin/pint --test` : réussi.
- `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress` : niveau 8, aucune erreur, aucune baseline ou suppression ajoutée.
- `composer validate --strict --no-check-publish`, `composer check-platform-reqs`, `composer audit --locked --no-interaction` : réussis, aucun avis de sécurité. Composer 2.8.5 émet des dépréciations sous PHP 8.5 ; les dépendances verrouillées sont inchangées.

Sept courses utilisent deux processus PHP indépendants et des fixtures réellement commitées : création rejouée, édition sous version, édition rejouée, suspension, parent masqué, parent archivé et commentaire masqué. Le test constate deux attentes de verrou dans `pg_stat_activity` avant de libérer les processus. Une seule écriture ou deux refus sont vérifiés avec les comptes de commentaires, révisions, audit, idempotence et outbox ; aucune boucle séquentielle n'est qualifiée de concurrence.

Les tests HTTP vérifient validation stricte, contenus suspects, session/CSRF, propriété, ancien résultat après nouvelle édition, invisibilité avant pagination/total, notes B16 après contribution et rollback sur panne de révision/audit/outbox/idempotence. La migration est réversible sans événement et refuse un retour qui détruirait des notifications de commentaires existantes.

## Validation générale après intégration de main

`origin/main` à `f1f6238e9deaa5d4577ed3c728777a98f1f4aa28` a été intégré dans la préparation B17 avant publication. Le seul conflit concernait `docs/AI_USAGE.md` : les interventions B14/B15/B16 et B35 sont conservées. Aucune modification métier de main n'a été réécrite.

| Commande depuis backend, PHP 8.5.10 | Résultat observé |
|---|---|
| `php vendor/bin/phpunit --testsuite Unit,Feature,Architecture` | **328 tests / 3563 assertions**, réussis |
| `php vendor/bin/phpunit --testsuite Integration` | **336 tests / 3043 assertions**, réussis |
| `php vendor/bin/phpunit tests/Integration/Collaboration/CommentNotificationsTest.php` | **7 tests / 199 assertions**, réussis |
| `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress` | Niveau 8, aucune erreur après ajout des notifications |
| `php vendor/bin/pint --test` | Réussi |
| `php ../scripts/generate-api-types.php --check` | **49 types API à jour**, OpenAPI 0.16.0 |
| `node scripts/validate-pack.mjs` et `node scripts/check-deployment-docs.mjs` depuis la racine | **18/18 et 7/7** |
| `git diff --check` et `git diff --cached --check` | Sans erreur |

La suite Integration a découvert ses fichiers avant l'ajout du test de notifications ; ce fichier a été exécuté séparément après la fin de la suite, sur la même base dédiée et le même code. Total de cas distincts : **671 tests / 6805 assertions**, dont **343 / 3242** PostgreSQL. Les contrôles ciblés précédents ne sont pas additionnés une deuxième fois.

Les sept cas de notifications démontrent une intention invisible sur une autre connexion avant commit, rollback commun, livraison à niveau de transaction zéro et déduplication. Ils vérifient absence d'auto-envoi/notification d'édition, message sans corps/courriel, visibilité et marquage après masquage du commentaire/parent, parent redevenu brouillon, auteur suspendu ou non vérifié ; le destinataire des trois derniers cas reste actif et vérifié. Le rejeu après retour à la visibilité ne recrée pas l'événement.

Le premier passage hors SQL a exécuté 298 tests / 3105 assertions, avec un échec `ApiInventoryTest` : les cinq routes B17 attendaient leur documentation OpenAPI. Le contrat ajouté et la suite finale ci-dessus résolvent cet échec. La vérification des corps JSON de notification exige réellement une chaîne avant l'inspection de leur contenu ; aucun cast ni ignore d'analyse ajouté.

## Limites et reprise

### Correction du navigateur simulé après la première CI B17

PR [#32](https://github.com/haas-projet/haas/pull/32), premier head `6737081bd51e930a01a2001218d74cf418a11f5b` : la [CI 37657236028](https://github.com/haas-projet/haas/actions/runs/37657236028) réussit sous PHP 8.5, mais échoue sous PHP 8.4 dans l'ancien test `IdempotencyTest::test_http_retry_returns_same_profile_with_one_version_and_audit_and_no_private_snapshot`. La reconnexion après une avance de 23 heures reçoit 419 ; les nouveaux cas B17 ne sont pas la cause de cet échec.

Le navigateur simulé conservait indéfiniment ses cookies, alors que la session expire après deux heures. Avec un cookie expiré encore envoyé, la lecture Laravel constate une ancienne ligne de session, puis la collecte peut la supprimer avant la sauvegarde ; le jeton CSRF de la réponse n'est alors pas persisté. Une collecte forcée à 100 % rend le défaut reproductible sous PHP 8.5 : le test ciblé échoue réellement à 419 (**1 test / 17 assertions**), avant correction.

`SpaHttpRequests` conserve désormais l'échéance par nom et valeur de cookie, retire les cookies expirés avant de construire le header XSRF et utilise l'horloge simulée `now()`. Les cookies sans échéance et les sauvegardes/restaurations de jars restent pris en charge. Le cas de session expirée transmet toujours volontairement le cookie périmé (`sendExpiredCookies: true`) et vérifie son refus 401 par le serveur. La collecte forcée reste dans le test d'idempotence ; aucun contrôle CSRF, traitement applicatif, dépendance ou configuration de production n'est assoupli.

Après correction, `php vendor/bin/phpunit tests/Integration/IdempotencyTest.php tests/Integration/MemberSessionTest.php` réussit : **20 tests / 139 assertions**. Les deux suites générales sont ensuite exécutées entièrement : Unit/Feature/Architecture **328 / 3563**, Integration **343 / 3242**, soit **671 tests distincts / 6805 assertions**, tous réussis sous PHP 8.5.10 et sur la base locale dédiée décrite plus haut. Pint et PHPStan niveau 8 réussissent. Le ciblage de 20 tests n'est pas additionné aux suites. La CI du prochain head sous PHP 8.4/8.5 doit encore être constatée avant intégration.

Revue automatisée, aucune identité ou approbation humaine simulée. B14/B15/B16 restent dans les PR #24/#25/#26 ; B11 a réellement été intégré dans main via #23. B17 reste séparé pendant la revue des prérequis. Le SHA final, la PR et la CI du commit exact seront rapportés après commit.

Les projets BC04/BC05 ne sont pas livrés par ce lot. Aucun navigateur/frontend, Qodana, BACKEND_GATE, GO_FRONTEND ou déploiement validé. PHP 8.4 local non exécuté ; la CI du SHA final devra couvrir PHP 8.4/8.5 et PostgreSQL 17. Le cluster local sera arrêté après les contrôles. Prochain lot communautaire : B18, propositions de solution.

## Reprise après synchronisation B14/B15/B16 — 7 octobre 2026

B14 230b83b, B15 a3eb9b9 et B16 d3d8407 sont publiés dans leurs PR existantes. B17 métier 238d5e96593cb7dca32844b50d4077eee6f0adca est conservé ; B16 d3d84073b6b380b9ced2d8d9f68f6821636341a0 rejoint ensuite cette branche par merge normal. Les trois conflits concernent seulement AI_USAGE, PROGRESS de Lamine et les empreintes ; les historiques et notes B17 sont conservés. Aucun fichier backend ne change par cette synchronisation documentaire, selon git diff HEAD -- backend ; les tests671/6805 précédents correspondent au même code. Publier B17 vers B16 et constater sa CI exacte ; aucune fusion de PR ou revue humaine présumée.

## Consolidation des fusions intermédiaires vers #24

La [CI 37662853388](https://github.com/haas-projet/haas/actions/runs/37662853388) du correctif `82344704f690cc49d5ae73c67d7cbd5e71cf78b3` est réellement verte sous PHP 8.4/8.5 et backend-ci ; chaque job PHP confirme **328 / 3563 hors SQL** et **343 / 3242 PostgreSQL**. Les métadonnées GitHub constatent ensuite des fusions externes : #32 dans B16 par `a3fd44213182215c28cd4ba9b7710ea389722a94`, #26 dans B15 par `f346985df99b65f022f7248243ac6ba605f46d16`, #25 dans B14 par `61c41841a51501064e902c21497489317d654d15`.

#25 avait été fusionnée avant l'intégration de B16/B17 dans B15. Le merge normal de B15 `f346985` dans B14 consolide donc ces lots pour #24 vers main, sans conflit. Le backend résultant ne diffère pas de `8234470`, vérifié par Git ; les tests locaux précédents et les deux jobs CI correspondent au même code, sans prétendre à une nouvelle exécution locale SQL. Contrôles de documentation et empreintes relancés, CI du nouveau head à constater. Aucune fusion main, revue humaine, gate ou réception finale présumée.

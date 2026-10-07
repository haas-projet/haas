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

Revue automatisée, aucune identité ou approbation humaine simulée. B14/B15/B16 restent dans les PR #24/#25/#26 ; B11 a réellement été intégré dans main via #23. B17 reste séparé pendant la revue des prérequis. Le SHA final, la PR et la CI du commit exact seront rapportés après commit.

Les projets BC04/BC05 ne sont pas livrés par ce lot. Aucun navigateur/frontend, Qodana, BACKEND_GATE, GO_FRONTEND ou déploiement validé. PHP 8.4 local non exécuté ; la CI du SHA final devra couvrir PHP 8.4/8.5 et PostgreSQL 17. Le cluster local sera arrêté après les contrôles. Prochain lot communautaire : B18, propositions de solution.

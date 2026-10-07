# Commandes backend — B01/B02/B03

Partir de `backend/`. PHP 8.4 minimum avec pdo_pgsql. B02 fournit les scripts qualité locaux ; B03 les exécute sur GitHub Actions avec PHP 8.4/8.5, résultats réels dans [B03_CI.md](quality/B03_CI.md). Composer 2.10.3 a été testé localement et en CI. L'ancien Composer 2.8.5 du poste émet des dépréciations sous PHP 8.5 : choisir une version actuelle depuis [le site officiel](https://getcomposer.org/download/). Aucune installation globale n'a été modifiée pendant B02.

Sur le poste de cette session, sélectionner PHP pour le terminal PowerShell seulement (adapter le chemin ailleurs) :

```powershell
$env:Path = 'C:\laragon\bin\php\php-8.5.10-nts-Win32-vs17-x64;' + $env:Path
php --version
composer install
composer check-platform-reqs
if (!(Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
```

Configurer le rôle et la base locale HAAS dans `.env`, sans secret suivi par Git. La création de la base n'est pas automatique. Vérifier explicitement sa cible avant `php artisan migrate`. B01 n'a lancé aucune migration sur le service PostgreSQL existant.

Servir uniquement `backend/public`, jamais la racine du dépôt :

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

`GET /up` renvoie `{"status":"ok"}` sans session ni SQL. Cette sonde valide le démarrage, pas la disponibilité de la base ou des services externes.

## Tests

`composer test` exécute les tests Unit/Feature/Architecture sans base. `composer test:architecture` cible uniquement les frontières Data/Services. Pour l'intégration, créer un serveur local/CI isolé et une base **jetable dédiée** nommée `haas_*_test`, avec le rôle `haas_test`. Les tests SQL doivent étendre `Tests\PostgresTestCase`, qui vérifie la cible avant `RefreshDatabase` ; ce dernier peut reconstruire les tables de la seule base de test.

```powershell
$env:DB_CONNECTION = 'pgsql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '5432' # Adapter au serveur de test isolé.
$env:DB_DATABASE = 'haas_bootstrap_test'
$env:DB_USERNAME = 'haas_test'
$env:DB_URL = ''
# Fournir DB_PASSWORD hors historique si ce serveur en exige un.
composer test:integration
```

Les variables système priment sur `.env.testing` : une cible héritée incorrecte est refusée. `.env.testing.example` fournit le modèle ; la copie `.env.testing` reste ignorée. Ne pas réutiliser ce terminal pour une migration applicative sans rétablir la configuration.

## Contrôles complémentaires

```powershell
composer lint
composer analyse
composer test
composer validate --strict --no-check-publish
composer audit --locked
php artisan route:list --json
```

`lint` vérifie le style sans modifier de fichier ; `composer format` applique les corrections Pint puis le diff doit être relu. `analyse` exécute PHPStan/Larastan au niveau 8 sur app, configuration, bootstrap, migrations, routes et tests, avec PHP cible 8.4. Aucune baseline ni exclusion d'erreurs n'est configurée. Le contrôle charge le conteneur Laravel : utiliser la configuration locale de développement, jamais des secrets de production.

Le test d'architecture inspecte les références PHP et les helpers HTTP connus dans `app/Data` et `app/Services`, sans exécuter les sources analysées. Les alias d'import sont résolus. Ce contrôle n'est pas une isolation de sécurité : références dynamiques et dépendances indirectes nécessitent toujours une revue. Résultats et test témoin dans [B02_QUALITY.md](quality/B02_QUALITY.md).

Depuis la racine : `node scripts/validate-pack.mjs`, `node scripts/check-deployment-docs.mjs`, `git diff --check`. Le validateur documentaire contrôle les fichiers livrés par Git et exige une preuve pour les statuts IN_REVIEW/DONE. Ne pas employer `--ignore-platform-reqs`.

## Entretien de l'idempotence — B13

`php artisan idempotency:prune` retire au plus 1000 intentions API expirées par appel, sans contenu métier ni donnée dans la sortie hors nombre retiré. `php artisan schedule:list` permet d'inspecter sa déclaration toutes les cinq minutes, sans chevauchement. L'exploitation devra activer et superviser le scheduler Laravel sur le VPS ; aucune tâche de production n'a été installée par B13. Une clé expirée est inutilisable dès son échéance même si la purge est en retard. Voir [le contrat](api/IDEMPOTENCY.md).

## CI GitHub — B03

Le workflow Backend CI exécute ces contrôles sur chaque PR et chaque push de main, avec PHP 8.4/8.5 et PostgreSQL dédié. Le contrôle final s'appelle `backend-ci`. Consulter l'onglet Checks de la PR ou `gh pr checks NUMERO --repo haas-projet/haas`. Une CI en attente ou absente ne constitue pas une réussite. Pour le run exact : `gh run view RUN_ID --repo haas-projet/haas` ; les logs doivent correspondre au dernier commit proposé.

## Contrat API et exploitation du socle

Depuis la racine : `php scripts/generate-api-types.php` régénère les schémas TypeScript dans docs/api/generated, puis `php scripts/generate-api-types.php --check` vérifie leur fraîcheur. Il n’y a aucune SPA ; ces types structurels ne remplacent pas les validations serveur. La preuve de compilation locale est dans [SOCLE_RECEPTION_PARTIELLE.md](quality/SOCLE_RECEPTION_PARTIELLE.md).

Depuis backend, avec la configuration locale appropriée : `php artisan notifications:deliver` livre au plus 100 intentions et `php artisan ops:check` contrôle SQL, échecs et retards des files. Ce dernier retourne 1 en cas de problème, sans données privées ; ce n’est pas une preuve de délivrabilité SMTP ou de santé du lab.

Sauvegarde/restauration : lire [OPERATIONS_RUNBOOK.md](deployment/OPERATIONS_RUNBOOK.md) avant d’utiliser scripts/ops/exercise-backup.php. Seules des bases locales dédiées de test sont admises ; cible de restauration neuve et vide, clés hors dépôt. L’outil ne se connecte pas à la production.

## Commentaires B17 — contrôles ciblés

Depuis backend, après avoir configuré et vérifié la base locale dédiée de test avec TestDatabaseGuard :

```powershell
php vendor/bin/phpunit tests/Unit/Collaboration/CommentMarkdownTest.php
php vendor/bin/phpunit tests/Integration/Collaboration/CommentsTest.php tests/Integration/Collaboration/CommentsConcurrencyTest.php tests/Integration/Collaboration/CommentRevisionsMigrationTest.php tests/Integration/Collaboration/CommentNotificationsTest.php
```

Les contrôles généraux restent composer lint, composer analyse, composer test et composer test:integration. NotificationOutbox exige un appel de livraison hors transaction métier ; notifications:deliver reprend les intentions commitées. Les tests emploient seulement des fixtures fictives et des processus PHP du dépôt. Le rollback B17 refuse de supprimer des événements comment.created existants ; ne pas effacer des données applicatives pour contourner ce refus. Preuves et cible PostgreSQL réellement utilisées : docs/quality/B17_COMMENTS.md.

Pour reproduire le parcours de session et d'idempotence corrigé après la première CI B17, sur cette même base dédiée :

```powershell
php vendor/bin/phpunit tests/Integration/IdempotencyTest.php tests/Integration/MemberSessionTest.php
```

Le navigateur simulé retire les cookies expirés avec l'horloge des tests ; le cas de cookie périmé volontairement envoyé exige toujours un refus serveur. Le test Idempotency force la collecte des sessions pour rendre la reconnexion après 23 heures déterministe. La preuve B17 conserve l'échec réel avant correction.


## Runtime B2 séparé — B38

Depuis backend : `php demo/artisan migrate --force`, `php demo/artisan demo:prune`, `php demo/artisan schedule:list` utilisent exclusivement la configuration et les migrations demo. Les tests SQL exigent deux bases locales dédiées avec rôles distincts, CONNECT métier refusé au rôle fictif : `php vendor/bin/phpunit tests/Feature/Demo tests/Integration/Demo`. Variables DEMO_DB_* et DEMO_IDEMPOTENCY_KEY propres ; aucune copie de la clé ou de l’environnement HAAS. Résultats réels et limites : docs/quality/B38_ISOLATION_20261007.md.

Pour B38 : renseigner DEMO_HAAS_DATABASE explicitement, indépendamment de DB_DATABASE. Les suites finales utilisent les deux DB dédiées haas_b38_review_test/haas_demo_b38_review_test sur 127.0.0.1:55447 et des clés éphémères non affichées. Commandes : php vendor/bin/phpunit --testsuite Unit,Feature,Architecture ; php vendor/bin/phpunit --testsuite Integration ; php vendor/bin/phpstan analyse --memory-limit=1G --no-progress ; php vendor/bin/pint --test ; php ../scripts/generate-api-types.php --check ; node scripts/validate-pack.mjs ; node scripts/check-deployment-docs.mjs depuis la racine pour les deux derniers. Les preuves distinguent les relances interrompues des suites achevées.

Résultat final B38 synchronisé avec main b76612d : Unit/Feature/Architecture 391 / 3850 et Integration 379 / 3438, total 770 / 7288 OK. PHPStan, Pint, Composer, 51 types API, pack 18/18 et déploiement documentaire 7/7 réussis. Les preuves exactes et les limites sont dans B38_ISOLATION_20261007.md ; CI et revue restent à recevoir.

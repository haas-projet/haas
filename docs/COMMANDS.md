# Commandes backend — B01/B02

Partir de `backend/`. PHP 8.4 minimum avec pdo_pgsql. B02 fournit les scripts qualité locaux ; la CI distante reste à créer en B03. Composer 2.10.3 a été testé avec PHP 8.5.10. L'ancien Composer 2.8.5 du poste émet des dépréciations sous PHP 8.5 : choisir une version actuelle depuis [le site officiel](https://getcomposer.org/download/). Aucune installation globale n'a été modifiée pendant B02.

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

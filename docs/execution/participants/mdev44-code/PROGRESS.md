# Progression — capsules/laboratoire (branche B38)

Cette branche `backend/capsules-laboratoire-b38-api-demo-b2` est dérivée directement de `origin/main` (7a8c672) et pas de `backend/capsules-laboratoire`. Elle ne consomme pas les enums de PR #12 : la brique B2 est explicitement isolée du reste du domaine capsules/laboratoire (cahier des charges §17 et §21). Les fichiers de suivi du domaine (PR #12 et PR #27) restent ouverts côté `backend/capsules-laboratoire`. Un conflit `add/add` sur ce fichier est attendu lors de la future consolidation ; il se résout en gardant les deux histoires de session.

## 2026-10-06 — Lot B38 · API de démonstration B2

Session dédiée au seul lot B38 (API de démonstration B2). Le lot est le seul des 13 lots de routes du domaine capsules/laboratoire dont les prérequis stricts (`B13 IdempotencyService`) sont déjà intégrés dans `origin/main` ; les 12 autres lots dépendent de B22 (capsules), B33 (lab) ou BV201 (cas) qui ne sont ni fusionnés ni ouverts en PR.

### Décisions clefs

- **Isolation B2.** Le service `App\Services\Idempotency\IdempotencyService` ne peut pas être utilisé : il exige un `User` persisté sur la connexion métier et une Policy du socle. B2 n’a ni session ni compte. Les value objects `IdempotencyKey` et `IdempotencyData` sont agnostiques du User et sont réutilisés tels quels pour la validation UUID v4, le hashage SHA-256 de la clé, la canonicalisation stricte de la charge et l’empreinte HMAC-SHA256.
- **Point de bascule de base.** Nouvelle classe `App\Models\Demo\DemoConnection` qui matérialise la bascule vers la base `demo` prévue par les §15/§24/§17 du cahier. `DemoConnection::NAME = null` tant que la connexion dédiée n’est pas câblée côté socle (fichiers responsable 1). Même protocole que `LabConnection` de B35.
- **Middlewares retirés route par route.** `ProtectSpaRequests` et `EnsureAccountIsActive` sont retirés uniquement sur le groupe B2 par `withoutMiddleware([...])` dans `backend/routes/api/capsules-lab.php`. Une alternative plus propre (groupe de middlewares dédié `b2` dans `bootstrap/app.php`) a été consignée pour arbitrage.

### Fichiers créés

- Schéma : `backend/database/migrations/2026_10_06_160000_create_demo_orders_table.php`, `backend/app/Models/Demo/{DemoConnection,DemoOrder}.php`, `backend/database/factories/Demo/DemoOrderFactory.php`.
- Service d'écriture : `backend/app/Data/Demo/{DemoOrderData,RecordedDemoOrder}.php`, `backend/app/Services/Demo/RecordDemoOrderService.php`.
- HTTP : `backend/app/Http/Requests/Demo/RecordDemoOrderRequest.php`, `backend/app/Http/Resources/Demo/DemoOrderResource.php`, `backend/app/Http/Controllers/Demo/RecordDemoOrderController.php`, groupe B2 ajouté dans `backend/routes/api/capsules-lab.php`.
- Purge : `backend/app/Services/Demo/PurgeExpiredDemoOrdersService.php` (logique, rétention 24 h par défaut, bornes 1 s – 30 j, batch 1000) et `backend/app/Console/Commands/PruneDemoOrders.php` (commande Artisan mince `demo:prune [--older-than=<s>]`).
- Tests : `backend/tests/Feature/Demo/RecordDemoOrderValidationTest.php` (5 méthodes, 16 cas), `backend/tests/Integration/Demo/RecordDemoOrderServiceTest.php` (6 méthodes, 10 cas), `backend/tests/Integration/Demo/RecordDemoOrderHttpTest.php` (5 méthodes, 5 cas), `backend/tests/Integration/Demo/PruneDemoOrdersTest.php` (6 méthodes, 6 cas).
- Documentation : `docs/quality/B38_B2_API.md`, `docs/api/openapi/capsules-lab.yaml` (opération `recordDemoOrder` + schémas), `docs/api/generated/haas-api.d.ts` (régénéré).

### Contrôles exécutés et résultats réels

| Commande | Résultat observé | Environnement |
|---|---|---|
| `composer lint` | PASS (Pint) | PHP 8.4.15 Laragon, Windows |
| `composer analyse` | **[OK] No errors** (PHPStan/Larastan) | idem |
| `composer test` | 267 tests / 2594 assertions ; **1 échec attendu** : `ApiInventoryTest` (route B38 absente de `docs/OPENAPI.yaml` racine, fichier responsable 1). Les 266 autres tests passent. | idem |
| `composer test:integration` | **154 tests / 1332 assertions, OK** dont 21 cas Demo B38 (10 service + 5 http + 6 purge) ; aucun test Lab B35 présent sur cette branche dérivée de `main` | PostgreSQL 17 sur `haas_capsules_test`, rôle `haas_test` ; `TestDatabaseGuard` accepté sans contournement |
| `php scripts/generate-api-types.php` | 30 types générés ; diff limité à `capsules_lab_DemoOrder` et `capsules_lab_DemoOrderInput` | PHP 8.4.15 |
| CI distante | **NON EXÉCUTÉE** à ce stade (branche non poussée) | — |

### Addendum 2026-10-06 — Purge `demo:prune`

Dernier élément manquant du verify B38 (« Aucune session HAAS utilisée et données bornées/**purgées** ») livré en 2 commits locaux : service `PurgeExpiredDemoOrdersService` + commande Artisan `demo:prune`. Rétention par défaut 24 h, inférée de la convention `api_idempotency` du §24 et de la règle §15 — le cahier ne fixe pas de valeur pour `demo_orders`, arbitrage ouvert consigné dans `docs/quality/B38_B2_API.md`. Option CLI `--older-than=<secondes>` bornée à [1 s, 30 j]. Portée stricte : la suppression n'opère que sur `demo_orders` via `DemoConnection::NAME` ; aucune autre table n'est accédée. Idempotente : seconde invocation = 0 ligne. Six tests d'intégration ajoutés (`PruneDemoOrdersTest`) ; `composer test:integration` passe de 148/1307 à **154/1332**. `composer test` reste à 267/2594 avec le seul échec attendu `ApiInventoryTest`.

### Limites et étapes suivantes

- B38 est backend seul : aucun composant React/service worker/kit B2 n’est livré. Hors périmètre pré-`GO_FRONTEND`.
- Pour que `composer test` soit entièrement vert, le responsable 1 doit ajouter dans `docs/OPENAPI.yaml` :

  ```yaml
    /api/v1/b2/demo-orders:
      $ref: './api/openapi/capsules-lab.yaml#/paths/~1api~1v1~1b2~1demo-orders'
  ```

- Pour que la purge s'exécute automatiquement, le responsable 1 doit ajouter dans `backend/routes/console.php` :

  ```php
  Schedule::command('demo:prune')->everyFifteenMinutes()->withoutOverlapping(5);
  ```

  (fréquence suggérée, à arbitrer selon la volumétrie). Tant que cette ligne n'est pas ajoutée, `demo:prune` doit être exécutée manuellement sur le VPS.
- Aucun fichier interdit n’a été touché : `config/`, `bootstrap/`, `phpunit.xml`, `.env.example`, `composer.json/lock`, `.github/workflows/`, `routes/api.php`, `routes/api/identity.php`, `routes/console.php`, `docs/OPENAPI.yaml` sont intacts.
- La connexion `demo` n’est pas câblée : les tables vivent sur la base par défaut. Trois fichiers du socle à étendre lors du câblage (voir `docs/quality/B38_B2_API.md` §Écarts).
- PR brouillon **#28** publiée ; CI distante rouge uniquement sur `ApiInventoryTest` (attendu). Les deux commits locaux de purge ne sont pas encore poussés.

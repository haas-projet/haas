# B38 — API de démonstration B2

## Résumé

Brique B2 isolée : `POST /api/v1/b2/demo-orders` enregistre une commande fictive de démonstration de façon idempotente par clé stable client (`Idempotency-Key`, UUID v4). Aucun paiement réel, aucune session HAAS, aucun cookie émis, aucune donnée métier HAAS lue ou écrite. Table dédiée `demo_orders` sur connexion conceptuellement isolée (point de bascule `App\Models\Demo\DemoConnection::NAME`, aligné sur le même protocole que `LabConnection` du lot B35).

## Périmètre livré

| Couche | Fichier | Rôle |
|---|---|---|
| Modèle | `backend/app/Models/Demo/DemoOrder.php` | Eloquent HasUuids, connexion dynamique |
| Modèle | `backend/app/Models/Demo/DemoConnection.php` | Point de bascule applicatif vers la base `demo` |
| Migration | `backend/database/migrations/2026_10_06_160000_create_demo_orders_table.php` | Table `demo_orders` + contraintes CHECK |
| Factory | `backend/database/factories/Demo/DemoOrderFactory.php` | Valeurs canoniques fictives respectant les CHECK |
| DTO | `backend/app/Data/Demo/DemoOrderData.php` | Payload borné, projection canonique pour empreinte |
| DTO | `backend/app/Data/Demo/RecordedDemoOrder.php` | Résultat typé `{order, replay}` |
| Service | `backend/app/Services/Demo/RecordDemoOrderService.php` | `insertOrIgnore` + relecture + comparaison d’empreinte + `IdempotencyConflict` |
| FormRequest | `backend/app/Http/Requests/Demo/RecordDemoOrderRequest.php` | Validation UUID v4 Idempotency-Key, body borné, mass-assignment interdit |
| Resource | `backend/app/Http/Resources/Demo/DemoOrderResource.php` | Projection publique sans empreinte ni donnée HAAS |
| Controller | `backend/app/Http/Controllers/Demo/RecordDemoOrderController.php` | Controller mince ; code 201/200 + `X-Idempotent-Replay` |
| Route | `backend/routes/api/capsules-lab.php` | `Route::prefix('b2')->withoutMiddleware([ProtectSpaRequests, EnsureAccountIsActive])->post('demo-orders', ...)` |
| OpenAPI | `docs/api/openapi/capsules-lab.yaml` | Opération `recordDemoOrder` + schémas `DemoOrderInput` / `DemoOrder` |
| Types générés | `docs/api/generated/haas-api.d.ts` | Régénéré via `php scripts/generate-api-types.php` |
| Service de purge | `backend/app/Services/Demo/PurgeExpiredDemoOrdersService.php` | Suppression bornée à `demo_orders` via `DemoConnection::NAME` ; batch 1000 ; rétention 24 h par défaut, bornée à 30 j |
| Commande Artisan | `backend/app/Console/Commands/PruneDemoOrders.php` | `demo:prune [--older-than=<s>]` ; sortie limitée au nombre de lignes retirées, aucun contenu journalisé |

## Contrat HTTP

- Préfixe `/api/v1` conservé pour la cohérence avec la convention §25 du cahier.
- Déploiement cible (§17/§21 du cahier) : hôte `demo-api.example.com` hors `.haas.example.com` ; aucun cookie du parent de confiance ne doit être émis ou accepté.
- En-tête `Idempotency-Key` **obligatoire**, UUID v4. Absente/invalide → 422 `VALIDATION_FAILED` sur le champ virtuel `idempotency_key`.
- Même clé + même charge → 200 avec `X-Idempotent-Replay: true` et la commande d’origine.
- Même clé + charge différente → 409 `IDEMPOTENCY_CONFLICT`, format d’erreur standard HAAS, aucune écriture, commande d’origine inchangée.
- Toute clé de corps inconnue est refusée (pas de mass-assignment implicite).
- Les commandes n’ont qu’un état, `confirmed`, posé à l’écriture.

## Idempotence B2 isolée du socle

- `App\Services\Idempotency\IdempotencyService` (B13) exige un `User` HAAS persisté sur la connexion métier et une Policy `participate`. Il n’est **pas** utilisé pour B2. B13 note déjà, dans `docs/api/IDEMPOTENCY.md`, que `api_idempotency` est « indépendante des démonstrations B1/B2 ».
- Les value objects `App\Data\Idempotency\IdempotencyKey` et `App\Data\Idempotency\IdempotencyData` restent agnostiques du User HAAS : B2 les réutilise pour la validation UUID v4, le hashage SHA-256 de la clé, la canonicalisation stricte de la charge (tri récursif, bornes de taille et de profondeur) et l’empreinte HMAC-SHA256 sous la clé applicative serveur. Choix à relire sous l’angle isolation.
- La `ROUTE_TARGET` passée à l’empreinte est stable (`POST /api/v1/b2/demo-orders`) et indépendante du routage effectif.

## Base séparée et point de bascule

- Les §15/§24/§17 du cahier décrivent une base `demo` distincte de `haas_app` et de `haas_lab`. Tant que la connexion `demo` n’est pas câblée dans `backend/config/database.php`, `backend/phpunit.xml` et la CI (fichiers responsable 1), modèle et migration utilisent la connexion par défaut via `DemoConnection::NAME = null`.
- Au moment du câblage, trois endroits doivent évoluer de concert : `DemoConnection::NAME` côté domaine, la connexion `demo` côté socle, la connexion de nettoyage et `RefreshDatabase` côté tests.

## Purge des commandes fictives

- Commande Artisan `demo:prune` (voir `backend/app/Console/Commands/PruneDemoOrders.php`) + service pur `PurgeExpiredDemoOrdersService`. La portée est strictement bornée à `demo_orders` sur `DemoConnection::NAME` ; aucune autre table n’est accédée.
- **Rétention par défaut : 24 heures** (`PurgeExpiredDemoOrdersService::DEFAULT_RETENTION_SECONDS = 86_400`). Le cahier ne fixe pas de valeur explicite pour `demo_orders` ; la valeur reprend la convention du §24 (« `api_idempotency` : Conservation proposée : 24 heures ») et la règle §15 (« Purge après le test ou par nettoyage de secours sous 24 heures »). **Arbitrage ouvert**.
- Option CLI secondaire `--older-than=<secondes>` (entier ≥ 1, borne haute 30 j = `MAXIMUM_RETENTION_SECONDS = 2_592_000`). Les valeurs hors domaine (non numériques, négatives, zéro, supérieures à 30 j) sont rejetées sans suppression (`Command::INVALID`).
- La commande est idempotente : une seconde invocation immédiate renvoie `0 commande(s) fictive(s) retirée(s).`. Elle supprime au plus 1000 lignes par appel (même borne que `IdempotencyService::pruneExpired()`) — la planification peut la rejouer pour drainer un retard sans tenir une transaction longue.
- La sortie ne contient que le nombre de lignes retirées ; aucune empreinte, aucun identifiant, aucun champ de ligne n’est journalisé.
- **Planification à ajouter par le responsable 1** dans `backend/routes/console.php` (fichier Ousseynou, auteur de tous les commits existants ; porte déjà `idempotency:prune` et `notifications:deliver`). Diff proposé :

  ```php
  Schedule::command('demo:prune')->everyFifteenMinutes()->withoutOverlapping(5);
  ```

  Fréquence suggérée : toutes les 15 minutes, avec `withoutOverlapping(5)`. À arbitrer selon la volumétrie prévue de la démonstration. Tant que cette ligne n’est pas ajoutée, l’exécution de `demo:prune` reste manuelle sur le VPS Systalink.

## Vérifications exécutées

| Contrôle | Résultat observé | Environnement |
|---|---|---|
| `composer lint` (`vendor/bin/pint --test`) | PASS | PHP 8.4.15, Windows |
| `composer analyse` (PHPStan/Larastan) | **[OK] No errors** | idem |
| `composer test` (Unit + Feature + Architecture) | 267 tests / 2594 assertions ; **1 échec** connu : `ApiInventoryTest` (B38 absent du `docs/OPENAPI.yaml` racine, voir « Écarts »). Les 266 autres tests passent. | idem |
| `composer test:integration` (PostgreSQL 17) | **154 tests / 1332 assertions, OK** (dont 21 cas Demo B38 : 10 service + 5 http + 6 purge ; aucun test Lab B35 présent sur cette branche dérivée de `main`) | `haas_capsules_test`, haas_test |
| `php scripts/generate-api-types.php` | 30 types générés, diff limité à `capsules_lab_DemoOrder` + `capsules_lab_DemoOrderInput` | PHP 8.4.15 |
| CI distante | **NON EXÉCUTÉE** à ce stade (branche non poussée) | — |

## Écarts à arbitrer avec le responsable 1

1. **Nom de migration sans préfixe de lot.** Dérogation assumée à `docs/execution/BACKEND_A_TROIS.md:110` ; même convention que B35. À arbitrer par le relecteur.
2. **Base `demo` non câblée côté socle.** `DemoConnection::NAME = null`, les commandes vivent sur la base par défaut. Trois fichiers responsable 1 à étendre lors du câblage : `backend/config/database.php` (connexion `demo`), `backend/phpunit.xml` (variables d’environnement de test), `.github/workflows/backend-ci.yml` (service PostgreSQL additionnel ou base supplémentaire).
3. **Stripage de middlewares.** `ProtectSpaRequests` et `EnsureAccountIsActive` sont retirés du groupe B2 par `withoutMiddleware([...])` dans `backend/routes/api/capsules-lab.php` (fichier responsable 3). Alternative plus propre à discuter : déclarer un groupe de middlewares dédié `b2` dans `backend/bootstrap/app.php` qui n’inclut ni `statefulApi()` ni `EnsureAccountIsActive`.
4. **Réutilisation des value objects B13.** `IdempotencyKey` et `IdempotencyData` sont des objets de valeur agnostiques du User, mais vivent dans `App\Data\Idempotency`. Choix à relire sous l’angle isolation stricte (ou extraction d’un namespace `App\Support\IdempotencyPrimitives` partagé).
5. **Ligne manquante dans `docs/OPENAPI.yaml`.** La fragment `docs/api/openapi/capsules-lab.yaml` déclare bien l’opération, mais `ApiInventoryTest` lit la racine `docs/OPENAPI.yaml` (fichier responsable 1) et ne voit pas la nouvelle route. Diff proposé :

   ```yaml
   # docs/OPENAPI.yaml, dans la section `paths:` entre `/api/v1/notifications/{id}` et `/api/v1/me/profile`
     /api/v1/b2/demo-orders:
       $ref: './api/openapi/capsules-lab.yaml#/paths/~1api~1v1~1b2~1demo-orders'
   ```

   Tant que cette ligne n’est pas ajoutée, `composer test` reste rouge sur ce seul test.
6. **Rétention par défaut `demo:prune`.** 24 heures retenues par analogie avec `api_idempotency` du §24 et la règle §15 ; la rétention n’est pas écrite pour `demo_orders`. Valeur à confirmer ou ajuster par le relecteur ; la constante `PurgeExpiredDemoOrdersService::DEFAULT_RETENTION_SECONDS` est le seul point à modifier côté domaine.
7. **Planification de `demo:prune` à poser dans `backend/routes/console.php`** (fichier responsable 1 ; voir diff dans « Purge des commandes fictives » ci-dessus). Tant que cette ligne n’est pas ajoutée, la purge reste manuelle.

## Limites et étapes suivantes

- B38 est un module **backend seulement** : aucun composant React, aucun service worker, aucun kit frontend B2 n’est livré. Le cahier §17 décrit un mini-formulaire React de démonstration qui reste hors périmètre, à livrer après `GO_FRONTEND`.
- La CI distante n’a pas encore observé B38 ; la branche doit être poussée et une PR brouillon ouverte pour que `backend-ci` s’exécute sur PHP 8.4 et 8.5.
- Aucun test de concurrence réelle n’est inclus (contrairement à B35). La contrainte unique SQL `demo_orders_idempotency_key_unique` protège déjà atomiquement contre deux insertions pour la même clé ; un test de course par processus séparés comme pour B1-05 pourra être ajouté lors d’un passage en revue si demandé.

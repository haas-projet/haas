# HANDOFF — capsules/laboratoire (branche B38)

Branche : `backend/capsules-laboratoire-b38-api-demo-b2`. Base : `origin/main` (`7a8c672`). Lot livré : **B38 — API de démonstration B2**. Statut proposé : `IN_REVIEW` à l’ouverture de la PR brouillon ; aucune revue humaine effectuée à ce stade.

## À quelle question ce lot répond

Deux envois d’un formulaire B2 avec la même clé stable (`Idempotency-Key`, UUID v4) et la même charge donnent une seule commande fictive. Une charge différente avec la même clé est rejetée. Aucune session HAAS, aucun cookie, aucune donnée métier HAAS n’est touché. Les commandes fictives sont bornées en durée par la commande Artisan `demo:prune` (rétention 24 h par défaut).

## Dépendances et contrat

- Prérequis fusionné dans `origin/main` : B13 Idempotence (`App\Data\Idempotency\IdempotencyKey`, `IdempotencyData`, `ApiExceptionRenderer` qui mappe `IdempotencyConflict` → 409 `IDEMPOTENCY_CONFLICT`).
- Aucun dépendance aux PR #12 (enums capsules/lab/comparaisons), #22, #23, #24, #25, #26, #27.
- Branche `backend/capsules-laboratoire-b35-brique-b1` (PR #27) non consommée. Elle introduit un pattern similaire (`LabConnection::NAME = null`) qui a inspiré `DemoConnection`.

## Route livrée

- `POST /api/v1/b2/demo-orders` (name: `demo.orders.record`).
- Entrée : header `Idempotency-Key` (UUID v4) + body `{order_ref, amount_minor, currency}`.
- Réponses : 201 création, 200 rejeu (header `X-Idempotent-Replay`), 409 conflit, 422 validation.
- Aucun `Set-Cookie`, aucune session démarrée (contrôle explicite dans les tests).

## Commande Artisan livrée

- `demo:prune [--older-than=<secondes>]` : supprime au plus 1000 commandes fictives expirées par invocation ; sortie limitée au nombre de lignes retirées. Rétention par défaut 24 h (`PurgeExpiredDemoOrdersService::DEFAULT_RETENTION_SECONDS`), bornée à 30 j max.
- Portée stricte : seule la table `demo_orders` sur `DemoConnection::NAME` est touchée.
- Planification à poser par le responsable 1 dans `backend/routes/console.php` ; diff proposé dans `docs/quality/B38_B2_API.md` et dans la PR.

## Prochaines étapes pour la propriétaire

1. Pousser la branche : `git push -u origin backend/capsules-laboratoire-b38-api-demo-b2` (deux pushes autorisés par la session).
2. Ouvrir une PR en brouillon vers `main` (B38 n’empile aucune autre PR du domaine).
3. Faire relire par un relecteur humain différent de l’autrice (par ex. `ousseynoufayeisidk-sys`, désigné comme relecteur principal de la tâche 3). Discuter en revue les quatre points d’arbitrage listés dans `docs/quality/B38_B2_API.md` : nom de migration, câblage `demo`, stripage des middlewares, réutilisation des value objects B13.
4. Après revue, demander au responsable 1 d’ajouter la ligne manquante dans `docs/OPENAPI.yaml` (fragment déjà prêt) pour que `ApiInventoryTest` passe.
5. Observer la CI distante (`backend-ci` PHP 8.4 + 8.5) après le push.
6. Si demandé lors de la revue, ajouter un test de concurrence réelle `pcntl`/`symfony/process` sur la contrainte unique `demo_orders_idempotency_key_unique`, sur le modèle de B35.

## Fichiers à ne pas toucher jusqu’à nouvel ordre

- `backend/config/*`, `backend/bootstrap/app.php`, `backend/phpunit.xml`, `backend/.env.example`, `backend/composer.*`, `.github/workflows/*`.
- `backend/routes/api.php`, `backend/routes/api/identity.php`.
- `docs/OPENAPI.yaml` (fichier responsable 1 ; diff proposé déjà écrit dans la note de PR).

## Contrôles réellement exécutés

- `composer lint` PASS, `composer analyse` **[OK] No errors**.
- `composer test` : 267 tests / 2594 assertions, 1 échec attendu `ApiInventoryTest` (route B38 absente de `docs/OPENAPI.yaml` racine, fichier responsable 1).
- `composer test:integration` : **154 tests / 1332 assertions, OK** (dont 21 cas Demo B38 : 10 service + 5 http + 6 purge).
- `php scripts/generate-api-types.php` : 30 types, diff limité à B38.
- CI distante PR #28 (run 37497715618, avant les deux commits de purge) : `PHP 8.4 / PostgreSQL 17` et `PHP 8.5 / PostgreSQL 17` échouent sur le seul `ApiInventoryTest` ; `backend-ci` échoue par dépendance. Rouge attendue tant que `docs/OPENAPI.yaml` n'est pas complété.

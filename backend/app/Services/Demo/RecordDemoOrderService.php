<?php

namespace App\Services\Demo;

use App\Data\Demo\DemoOrderData;
use App\Data\Demo\RecordedDemoOrder;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Models\Demo\DemoConnection;
use App\Models\Demo\DemoOrder;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Service transactionnel de la brique B2 (lot B38) : enregistre une commande
 * fictive de démonstration de façon idempotente, à partir d'une clé client
 * stable (en-tête `Idempotency-Key`) et d'une charge canonique bornée.
 *
 * Isolation : aucune lecture ni écriture sur la base métier HAAS, aucune
 * session, aucune Policy du socle. L'invariant « une clé = une commande »
 * est porté par la contrainte unique SQL `demo_orders_idempotency_key_unique`.
 * `insertOrIgnore` matérialise la décision atomique :
 *   - inserted=1 → commande nouvelle (replay=false)
 *   - inserted=0 → clé déjà vue : relecture et comparaison des empreintes de
 *     charge. Même empreinte → replay=true (rejeu identique attendu). Empreinte
 *     différente → `IdempotencyConflict` ; aucune écriture, aucune commande
 *     existante écrasée (rendu 409 `IDEMPOTENCY_CONFLICT` par le socle HTTP).
 *
 * Les value objects `IdempotencyKey` et `IdempotencyData` sont réutilisés pour
 * leur canonicalisation stricte (UUID v4, SHA-256 de la clé, tri récursif +
 * HMAC SHA-256 de la charge sous clé applicative) : ils ne dépendent ni du
 * `User` HAAS ni d'une table métier. Le `routeTarget` passé est la cible
 * canonique de B2 (`POST /api/v1/b2/demo-orders`), stable d'une version à
 * l'autre indépendamment du routage HTTP effectif.
 *
 * Aucun paiement réel n'est manipulé. Les données fictives sont bornées par
 * `guard()` avant toute écriture.
 */
final class RecordDemoOrderService
{
    /**
     * Cible idempotente stable du service, utilisée pour l'empreinte HMAC.
     * Elle reste indépendante du routage HTTP effectif ; la modifier
     * invaliderait toutes les empreintes stockées encore vivantes.
     */
    public const ROUTE_TARGET = 'POST /api/v1/b2/demo-orders';

    public function __construct(private readonly Encrypter $encrypter) {}

    public function handle(DemoOrderData $data, string $idempotencyKey): RecordedDemoOrder
    {
        $this->guard($data);
        $key = new IdempotencyKey($idempotencyKey);
        $fingerprint = (new IdempotencyData(self::ROUTE_TARGET, $key, $data->canonical()))
            ->fingerprint($this->encrypter->getKey());

        return DB::connection(DemoConnection::NAME)->transaction(function () use ($data, $key, $fingerprint): RecordedDemoOrder {
            $orderUuid = (string) Str::uuid();
            $now = now();

            // insertOrIgnore sur la contrainte unique (idempotency_key_hash) : atomique,
            // pas de course possible entre SELECT et INSERT. Le modèle fixe la connexion
            // et la table : aucune chaîne littérale `demo_orders` n'est écrite ici.
            $inserted = DemoOrder::query()->insertOrIgnore([
                'id' => $orderUuid,
                'idempotency_key_hash' => $key->hash,
                'payload_hash' => $fingerprint,
                'order_ref' => $data->orderRef,
                'amount_minor' => $data->amountMinor,
                'currency' => $data->currency,
                'state' => 'confirmed',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === 1) {
                /** @var DemoOrder $order */
                $order = DemoOrder::query()->whereKey($orderUuid)->firstOrFail();

                return new RecordedDemoOrder(order: $order, replay: false);
            }

            // Clé déjà vue : relire la ligne et comparer les empreintes de charge.
            // Un `first()` nul signifie que `insertOrIgnore=0` sans ligne porteuse :
            // invariant SQL enfreint par une écriture externe, on refuse plutôt que
            // d'improviser.
            $existing = DemoOrder::query()
                ->where('idempotency_key_hash', $key->hash)
                ->firstOr(function (): never {
                    throw (new ModelNotFoundException)->setModel(DemoOrder::class);
                });

            if (! hash_equals($existing->payload_hash, $fingerprint)) {
                throw new IdempotencyConflict;
            }

            return new RecordedDemoOrder(order: $existing, replay: true);
        });
    }

    /**
     * Ceinture de validation indépendante du transport HTTP : garantit qu'aucune
     * valeur hors contrat n'arrive dans l'INSERT, même en cas de contournement
     * du FormRequest. Les règles sont identiques à `RecordDemoOrderRequest`.
     */
    private function guard(DemoOrderData $data): void
    {
        Validator::make([
            'order_ref' => $data->orderRef,
            'amount_minor' => $data->amountMinor,
            'currency' => $data->currency,
        ], [
            'order_ref' => ['required', 'string', 'max:64', 'regex:/^\S(.*\S)?$/D'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/D'],
        ], [], [
            'order_ref' => 'référence de commande',
            'amount_minor' => 'montant',
            'currency' => 'devise',
        ])->validate();
    }
}

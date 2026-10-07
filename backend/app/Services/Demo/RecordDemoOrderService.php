<?php

namespace App\Services\Demo;

use App\Data\Demo\DemoOrderData;
use App\Data\Demo\RecordedDemoOrder;
use App\Data\Idempotency\IdempotencyData;
use App\Data\Idempotency\IdempotencyKey;
use App\Exceptions\Demo\DemoCapacityReached;
use App\Exceptions\Idempotency\IdempotencyConflict;
use App\Models\Demo\DemoConnection;
use App\Models\Demo\DemoOrder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Enregistrement fictif atomique, cl? HMAC B2 propre, garantie 24 heures et stockage born?. */
final class RecordDemoOrderService
{
    /**
     * Cible idempotente stable du service, utilisée pour l'empreinte HMAC.
     * Elle reste indépendante du routage HTTP effectif ; la modifier
     * invaliderait toutes les empreintes stockées encore vivantes.
     */
    public const ROUTE_TARGET = 'POST /api/v1/b2/demo-orders';

    public function handle(DemoOrderData $data, string $idempotencyKey): RecordedDemoOrder
    {
        $this->guard($data);
        $key = new IdempotencyKey($idempotencyKey);
        $fingerprint = (new IdempotencyData(self::ROUTE_TARGET, $key, $data->canonical()))
            ->fingerprint($this->fingerprintKey());

        return DB::connection(DemoConnection::NAME)->transaction(function () use ($data, $key, $fingerprint): RecordedDemoOrder {
            // La purge et l'enregistrement sérialisent leurs décisions, même entre processus.
            DB::connection(DemoConnection::NAME)->select('SELECT pg_advisory_xact_lock(238038)');
            DemoOrder::query()->where('idempotency_key_hash', $key->hash)->where('expires_at', '<=', now())->delete();
            if (! DemoOrder::query()->where('idempotency_key_hash', $key->hash)->exists() && DemoOrder::query()->count() >= 5000) {
                throw new DemoCapacityReached;
            }
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
                'expires_at' => $now->copy()->addDay(),
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
            'order_ref' => ['required', 'string', 'regex:/^demo-[0-9]{4}$/D'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:1000000'],
            'currency' => ['required', 'string', 'in:EUR'],
        ], [], [
            'order_ref' => 'référence de commande',
            'amount_minor' => 'montant',
            'currency' => 'devise',
        ])->validate();
    }

    private function fingerprintKey(): string
    {
        $key = config('demo.idempotency_key');
        if (! is_string($key) || strlen($key) < 32) {
            throw new \RuntimeException('Configurer une clé B2 indépendante de 32 caractères minimum.');
        }

        return $key;
    }
}

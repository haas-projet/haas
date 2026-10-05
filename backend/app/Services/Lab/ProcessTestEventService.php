<?php

namespace App\Services\Lab;

use App\Data\Lab\ProcessedTestEvent;
use App\Data\Lab\TestEventData;
use App\Models\Lab\LabConnection;
use App\Models\Lab\TestEvent;
use App\Models\Lab\TestOrder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Service transactionnel de la brique B1 : ingère un événement de test fictif
 * et produit la commande fictive associée. L'invariant d'idempotence est porté
 * par la contrainte unique `(run_id, event_id)` côté base : un rejeu identique
 * renvoie la même commande sans créer d'écriture supplémentaire. Le résultat
 * typé indique explicitement s'il s'agit d'un rejeu (B1-02) ou d'une création
 * nouvelle (B1-01).
 *
 * Lot B35. Aucun paiement réel n'est manipulé.
 */
final class ProcessTestEventService
{
    public function handle(TestEventData $data): ProcessedTestEvent
    {
        $this->guard($data);

        return DB::connection(LabConnection::NAME)->transaction(function () use ($data): ProcessedTestEvent {
            $now = now();
            $eventUuid = (string) Str::uuid();

            // insertOrIgnore sur la contrainte unique (run_id, event_id) : atomique,
            // sans course entre SELECT et INSERT. Le modèle fixe la connexion ciblée
            // et sa table : aucune chaîne littérale `test_events` n'est écrite ici.
            $inserted = TestEvent::query()->insertOrIgnore([
                'id' => $eventUuid,
                'run_id' => $data->runId,
                'event_id' => $data->eventId,
                'order_ref' => $data->orderRef,
                'amount_minor' => $data->amountMinor,
                'currency' => $data->currency,
                'received_at' => $data->receivedAt,
                'processed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === 1) {
                $order = TestOrder::query()->create([
                    'run_id' => $data->runId,
                    'source_event_id' => $eventUuid,
                    'order_ref' => $data->orderRef,
                    'amount_minor' => $data->amountMinor,
                    'currency' => $data->currency,
                ]);

                return new ProcessedTestEvent(order: $order, duplicate: false);
            }

            // (run_id, event_id) déjà pris : récupérer l'événement et sa commande.
            // Note : un rejeu avec un payload différent (même clé mais autre montant,
            // référence ou devise) renvoie la commande d'origine inchangée. Cette
            // décision — préférer l'invariant d'idempotence à une erreur 409 — reste
            // à arbitrer par le relecteur ; voir aussi
            // ProcessTestEventTest::test_replay_with_different_payload_returns_original.
            /** @var TestEvent $event */
            $event = TestEvent::query()
                ->where('run_id', $data->runId)
                ->where('event_id', $data->eventId)
                ->firstOrFail();

            $order = $event->order;
            if ($order === null) {
                // Un événement sans commande ne peut provenir que d'une écriture externe
                // au service : l'invariant est enfreint, on refuse plutôt que d'improviser.
                throw (new ModelNotFoundException)
                    ->setModel(TestOrder::class, [$event->getKey()]);
            }

            return new ProcessedTestEvent(order: $order, duplicate: true);
        });
    }

    private function guard(TestEventData $data): void
    {
        Validator::make([
            'run_id' => $data->runId,
            'event_id' => $data->eventId,
            'order_ref' => $data->orderRef,
            'amount_minor' => $data->amountMinor,
            'currency' => $data->currency,
        ], [
            'run_id' => ['required', 'uuid'],
            'event_id' => ['required', 'string', 'max:64', 'regex:/^\S(.*\S)?$/D'],
            'order_ref' => ['required', 'string', 'max:64', 'regex:/^\S(.*\S)?$/D'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/D'],
        ], [], [
            'run_id' => 'identifiant de run',
            'event_id' => 'identifiant d\'événement',
            'order_ref' => 'référence de commande',
            'amount_minor' => 'montant',
            'currency' => 'devise',
        ])->validate();
    }
}

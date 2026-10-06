<?php

namespace App\Services\Notifications;

use App\Data\Notifications\NotificationEvent;
use App\Exceptions\NotificationStorageFailed;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class NotificationOutbox
{
    public function record(NotificationEvent $event): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || DB::transactionLevel() < 1) {
            throw new LogicException('L’intention de notification exige la transaction métier PostgreSQL.');
        }
        try {
            DB::table('notification_outbox')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'recipient_id' => $event->recipientId, 'event_id' => $event->eventId,
                'kind' => $event->kind, 'created_at' => now()->utc(),
            ]);
        } catch (QueryException) {
            throw new NotificationStorageFailed('L’intention de notification n’a pas été enregistrée.');
        }
    }

    public function deliver(): int
    {
        // Un worker séparé voit seulement les intentions commitées. Aucun appel depuis une transaction métier.
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Livrer les notifications après commit, depuis un processus distinct.');
        }
        try {
            return DB::transaction(function (): int {
                $rows = DB::table('notification_outbox')->whereNull('delivered_at')->orderBy('created_at')->orderBy('id')
                    ->limit(100)->lock('FOR UPDATE SKIP LOCKED')->get();
                foreach ($rows as $row) {
                    DB::table('internal_notifications')->insertOrIgnore([
                        'id' => $row->id, 'event_id' => $row->event_id, 'recipient_id' => $row->recipient_id,
                        'kind' => $row->kind, 'created_at' => $row->created_at,
                    ]);
                    DB::table('notification_outbox')->where('id', $row->id)->update(['delivered_at' => now()->utc()]);
                }

                return $rows->count();
            });
        } catch (QueryException) {
            // Le métier déjà commité subsiste ; l'intention non livrée reste disponible pour reprise.
            throw new NotificationStorageFailed('Livraison différée ; les intentions restent à reprendre.');
        }
    }
}

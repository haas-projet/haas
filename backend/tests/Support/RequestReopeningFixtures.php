<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait RequestReopeningFixtures
{
    /** @param list<string> $parentIds */
    private function removeReopeningFixtures(array $parentIds): void
    {
        if ($parentIds === []) {
            return;
        }
        $driver = config('database.default');
        $connection = config('database.connections.'.$driver);
        if (! is_string($driver) || ! is_array($connection)) {
            throw new \LogicException('Configuration de fixtures B20 invalide.');
        }
        TestDatabaseGuard::check(config('app.env'), $driver, $connection);
        DB::transaction(function () use ($parentIds): void {
            // This bypass exists only in guarded tests, for their UUID fixtures.
            // Production rollback still refuses every retained historical fact.
            DB::statement('LOCK TABLE request_reopenings IN ACCESS EXCLUSIVE MODE');
            $ids = DB::table('request_reopenings')->whereIn('request_id', $parentIds)->pluck('id')->all();
            foreach (['internal_notifications', 'notification_outbox'] as $table) {
                DB::table($table)->where('kind', 'request.reopened')->whereIn('event_id', $ids)->delete();
            }
            DB::statement('ALTER TABLE request_reopenings DISABLE TRIGGER b20_request_reopening_guard');
            try {
                DB::table('request_reopenings')->whereIn('id', $ids)->delete();
            } finally {
                DB::statement('ALTER TABLE request_reopenings ENABLE TRIGGER b20_request_reopening_guard');
            }
        });
        $resolutionIds = DB::table('resolutions')->whereIn('request_id', $parentIds)->pluck('id')->all();
        foreach (['internal_notifications', 'notification_outbox'] as $table) {
            DB::table($table)->where('kind', 'resolution.accepted')->whereIn('event_id', $resolutionIds)->delete();
        }
        DB::table('resolutions')->whereIn('id', $resolutionIds)->delete();
        $proposalIds = DB::table('proposals')->whereIn('request_id', $parentIds)->pluck('id')->all();
        DB::table('proposal_revisions')->whereIn('proposal_id', $proposalIds)->delete();
        DB::table('proposals')->whereIn('id', $proposalIds)->delete();
    }
}

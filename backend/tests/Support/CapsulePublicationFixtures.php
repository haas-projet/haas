<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Mdev44\DomainTables;

trait CapsulePublicationFixtures
{
    protected function tearDown(): void
    {
        try {
            // Fixtures seulement : les vrais downgrades doivent conserver les données.
            TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
            foreach (['internal_notifications', 'notification_outbox'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->whereIn('kind', ['capsule.review.changes_requested', 'capsule.version.published'])->delete();
                }
            }
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }
}

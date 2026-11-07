<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CapsuleReviewNotificationFixtures
{
    protected function tearDown(): void
    {
        try {
            // Remise à zéro des seules fixtures sur la base de tests dédiée.
            // Le downgrade de production refuse de perdre événements ou snapshots.
            TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
            foreach (['internal_notifications', 'notification_outbox'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('kind', 'capsule.review.changes_requested')->delete();
                }
            }
            Schema::dropIfExists('capsule_version_reviews');
        } finally {
            parent::tearDown();
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['notification_outbox', 'internal_notifications'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_kind");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_kind CHECK (kind IN ('profile.moderated', 'comment.created', 'capsule.review.changes_requested'))");
        }
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            // Même ordre que la livraison : outbox avant boîte interne.
            // Les verrous gardent contrôle et DDL dans une seule photographie.
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::statement("LOCK TABLE {$table} IN ACCESS EXCLUSIVE MODE");
                }
            }
            // Les événements stables servent toujours à la déduplication et à la reprise.
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                if (Schema::hasTable($table) && DB::table($table)->where('kind', 'capsule.review.changes_requested')->exists()) {
                    throw new RuntimeException('Retour B24 refusé : événements de revue à conserver.');
                }
            }
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_kind");
                    DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_kind CHECK (kind IN ('profile.moderated', 'comment.created'))");
                }
            }
        });
    }
};

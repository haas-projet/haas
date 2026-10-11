<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string KIND = 'proposal.created';

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->lockTables();
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                // Composer la contrainte installée conserve les types des autres lots.
                $this->replaceCheck($table, '('.$this->installedCheck($table).") OR kind = '".self::KIND."'");
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->lockTables();
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                if (Schema::hasTable($table) && DB::table($table)->where('kind', self::KIND)->exists()) {
                    throw new RuntimeException('Retour B18 refusé : événements de propositions à conserver.');
                }
            }
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                if (Schema::hasTable($table)) {
                    $this->replaceCheck($table, '('.$this->installedCheck($table).") AND kind <> '".self::KIND."'");
                }
            }
        });
    }

    private function lockTables(): void
    {
        // Même ordre que la livraison : outbox avant boîte interne.
        foreach (['notification_outbox', 'internal_notifications'] as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("LOCK TABLE {$table} IN ACCESS EXCLUSIVE MODE");
            }
        }
    }

    private function installedCheck(string $table): string
    {
        $expression = DB::scalar('SELECT pg_get_expr(conbin, conrelid) FROM pg_constraint WHERE conrelid = CAST(? AS regclass) AND conname = ? AND contype = ?', [$table, $table.'_kind', 'c']);
        if (! is_string($expression) || $expression === '') {
            throw new RuntimeException('Contrainte de notification installée introuvable.');
        }

        return $expression;
    }

    private function replaceCheck(string $table, string $expression): void
    {
        // Noms fermés et expression issue du catalogue PostgreSQL, jamais du membre.
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_kind");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_kind CHECK ({$expression})");
    }
};

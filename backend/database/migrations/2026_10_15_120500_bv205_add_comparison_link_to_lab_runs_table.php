<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $database = DB::connection('pgsql');
        if ($database->getDriverName() !== 'pgsql') {
            throw new RuntimeException('BV205 exige la connexion applicative PostgreSQL.');
        }
        $database->transaction(function () use ($database): void {
            // ALTER lab_runs : ajoute comparison_id (nullable, FK vers
            // comparison_runs sur RESTRICT) et comparison_side (nullable,
            // CHECK baseline/candidate). Unicité (comparison_id,
            // comparison_side) et CHECK « les deux colonnes ensemble ou aucune ».
            Schema::connection('pgsql')->table('lab_runs', function (Blueprint $table): void {
                $table->foreignUuid('comparison_id')->nullable()->constrained('comparison_runs')->restrictOnDelete();
                $table->string('comparison_side', 16)->nullable();
            });
            $database->statement("ALTER TABLE lab_runs ADD CONSTRAINT lab_runs_comparison_side_values CHECK (comparison_side IS NULL OR comparison_side IN ('baseline','candidate'))");
            $database->statement(<<<'SQL'
                ALTER TABLE lab_runs ADD CONSTRAINT lab_runs_comparison_pair_consistent CHECK (
                    (comparison_id IS NULL AND comparison_side IS NULL)
                    OR (comparison_id IS NOT NULL AND comparison_side IS NOT NULL)
                )
            SQL);
            $database->statement('CREATE UNIQUE INDEX lab_runs_comparison_side_unique ON lab_runs (comparison_id, comparison_side) WHERE comparison_id IS NOT NULL');
        });
    }

    public function down(): void
    {
        // Fidèle à l'ajout. Tolérant : si la table ou la colonne est absente,
        // no-op. Jamais CASCADE. Si des lignes lab_runs référencent une
        // comparison_id, le retrait est refusé (comme le down de B33 refuse
        // les lignes résiduelles) : on protège l'historique de BV205+.
        $connection = DB::connection('pgsql');
        if (! Schema::connection('pgsql')->hasTable('lab_runs')) {
            return;
        }
        if (! Schema::connection('pgsql')->hasColumn('lab_runs', 'comparison_id')) {
            return;
        }
        $connection->transaction(function () use ($connection): void {
            $linked = $connection->table('lab_runs')->whereNotNull('comparison_id')->exists();
            if ($linked) {
                throw new RuntimeException('Retour BV205 refusé : des runs de comparaison sont rattachés.');
            }
            $connection->statement('DROP INDEX IF EXISTS lab_runs_comparison_side_unique');
            $connection->statement('ALTER TABLE lab_runs DROP CONSTRAINT IF EXISTS lab_runs_comparison_pair_consistent');
            $connection->statement('ALTER TABLE lab_runs DROP CONSTRAINT IF EXISTS lab_runs_comparison_side_values');
            Schema::connection('pgsql')->table('lab_runs', function (Blueprint $table): void {
                $table->dropForeign(['comparison_id']);
                $table->dropColumn(['comparison_id', 'comparison_side']);
            });
        });
    }
};

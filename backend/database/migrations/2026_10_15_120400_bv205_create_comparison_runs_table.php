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
            $schema = Schema::connection('pgsql');
            $schema->create('comparison_runs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('profile_id')->constrained('comparison_profiles')->restrictOnDelete();
                $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
                $table->uuid('request_key');
                $table->string('state', 16)->default('queued');
                $table->string('outcome', 16)->nullable();
                $table->char('input_digest', 64);
                $table->uuid('seed');
                $table->jsonb('env_snapshot');
                $table->timestampTz('started_at')->nullable();
                $table->timestampTz('finished_at')->nullable();
                $table->timestampTz('created_at');
                $table->unique(['actor_id', 'request_key']);
                $table->index(['state', 'created_at']);
                $table->index(['profile_id', 'created_at']);
            });
            // États et conclusions issus de App\Enums\Comparisons\ComparisonState
            // et App\Enums\Comparisons\ComparisonOutcome (CAHIER §46 ligne 1708).
            $database->statement("ALTER TABLE comparison_runs ADD CONSTRAINT comparison_runs_state_values CHECK (state IN ('queued','running','completed','error','timed_out'))");
            $database->statement("ALTER TABLE comparison_runs ADD CONSTRAINT comparison_runs_outcome_values CHECK (outcome IS NULL OR outcome IN ('improved','unchanged','regressed','mixed','inconclusive'))");
            // L'outcome n'est jamais écrit par les routes publiques : BV205
            // livre la table sans conclusion ; BV208 fera basculer completed →
            // outcome par le réconciliateur. Tant qu'on n'est pas 'completed',
            // outcome doit rester NULL.
            $database->statement('ALTER TABLE comparison_runs ADD CONSTRAINT comparison_runs_outcome_requires_terminal CHECK (outcome IS NULL OR state = \'completed\')');
            $database->statement("ALTER TABLE comparison_runs ADD CONSTRAINT comparison_runs_input_digest_shape CHECK (input_digest ~ '^[0-9a-f]{64}$')");
            $database->statement(<<<'SQL'
                ALTER TABLE comparison_runs ADD CONSTRAINT comparison_runs_dates_consistent CHECK (
                    (started_at IS NULL OR started_at >= created_at)
                    AND (finished_at IS NULL OR finished_at >= created_at)
                    AND (started_at IS NULL OR finished_at IS NULL OR finished_at >= started_at)
                    AND (state <> 'queued' OR (started_at IS NULL AND finished_at IS NULL))
                    AND (state <> 'running' OR (started_at IS NOT NULL AND finished_at IS NULL))
                    AND (state NOT IN ('completed','error','timed_out') OR finished_at IS NOT NULL)
                )
            SQL);
            $database->statement("ALTER TABLE comparison_runs ADD CONSTRAINT comparison_runs_env_snapshot_is_object CHECK (jsonb_typeof(env_snapshot) = 'object' AND octet_length(env_snapshot::text) <= 20000)");
        });
    }

    public function down(): void
    {
        // Fidèle : retire la table créée par cette migration. Tolérant : si la
        // table est absente, no-op. Les enfants lab_runs (BV205 colonne
        // comparison_id) sont retirés par leur propre down() avant celui-ci,
        // ou par DomainTables::dropAll(). Jamais CASCADE.
        if (! Schema::connection('pgsql')->hasTable('comparison_runs')) {
            return;
        }
        DB::connection('pgsql')->transaction(function (): void {
            Schema::connection('pgsql')->drop('comparison_runs');
        });
    }
};

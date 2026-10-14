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
            throw new RuntimeException('Le contrôle B33 exige la connexion applicative PostgreSQL.');
        }
        $database->transaction(function () use ($database): void {
            $schema = Schema::connection('pgsql');
            $schema->create('lab_definitions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('version_id')->unique()->constrained('capsule_versions')->restrictOnDelete();
                $table->string('runner_key', 40);
                $table->string('release', 64);
                $table->string('module_version', 32);
                $table->char('source_commit', 40);
                foreach (['code_digest', 'suite_digest', 'version_digest'] as $digest) {
                    $table->char($digest, 64);
                }
                $table->boolean('enabled')->default(false);
                $table->timestampTz('created_at');
            });
            $database->statement("ALTER TABLE lab_definitions ADD CONSTRAINT lab_definitions_runner_fixed CHECK (runner_key = 'b1.idempotent.v1')");
            $database->statement("ALTER TABLE lab_definitions ADD CONSTRAINT lab_definitions_release_shape CHECK (release ~ '^[a-z0-9][a-z0-9._-]{0,63}$' AND module_version ~ '^[0-9]+[.][0-9]+[.][0-9]+$' AND source_commit ~ '^[0-9a-f]{40}$')");
            foreach (['code_digest', 'suite_digest', 'version_digest'] as $digest) {
                $database->statement("ALTER TABLE lab_definitions ADD CONSTRAINT lab_definitions_{$digest}_shape CHECK ({$digest} ~ '^[0-9a-f]{64}$')");
            }
            $schema->create('lab_runs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('definition_id')->constrained('lab_definitions')->restrictOnDelete();
                $table->foreignUuid('version_id')->constrained('capsule_versions')->restrictOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
                $table->uuid('request_key');
                $table->string('scenario', 32);
                $table->string('release', 64);
                $table->string('module_version', 32);
                $table->char('source_commit', 40);
                foreach (['code_digest', 'suite_digest', 'version_digest'] as $digest) {
                    $table->char($digest, 64);
                }
                $table->string('state', 16)->default('queued');
                $table->timestampTz('created_at');
                $table->timestampTz('started_at')->nullable();
                $table->timestampTz('finished_at')->nullable();
                $table->integer('duration_ms')->nullable();
                $table->unique(['user_id', 'request_key']);
                $table->index(['state', 'created_at']);
                $table->index(['version_id', 'created_at']);
            });
            $database->statement("ALTER TABLE lab_runs ADD CONSTRAINT lab_runs_scenario_fixed CHECK (scenario IN ('nominal_event','duplicate_event','distinct_events','invalid_event'))");
            $database->statement("ALTER TABLE lab_runs ADD CONSTRAINT lab_runs_state_values CHECK (state IN ('queued','running','passed','failed','error','timed_out'))");
            $database->statement(<<<'SQL'
                ALTER TABLE lab_runs ADD CONSTRAINT lab_runs_dates_consistent CHECK (
                    (started_at IS NULL OR started_at >= created_at)
                    AND (finished_at IS NULL OR finished_at >= created_at)
                    AND (started_at IS NULL OR finished_at IS NULL OR finished_at >= started_at)
                    AND (duration_ms IS NULL OR duration_ms >= 0)
                    AND (state <> 'queued' OR (started_at IS NULL AND finished_at IS NULL AND duration_ms IS NULL))
                    AND (state <> 'running' OR (started_at IS NOT NULL AND finished_at IS NULL AND duration_ms IS NULL))
                    AND (state NOT IN ('passed','failed','error','timed_out') OR finished_at IS NOT NULL)
                    AND (state NOT IN ('passed','failed') OR (started_at IS NOT NULL AND duration_ms IS NOT NULL))
                )
                SQL);
            // B34 owns the shared operation/member reservation, including comparison children.
            $database->statement("CREATE UNIQUE INDEX lab_runs_one_running_globally ON lab_runs (state) WHERE state = 'running'");
            $schema->create('lab_results', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('run_id')->unique()->constrained('lab_runs')->restrictOnDelete();
                $table->string('state', 16);
                $table->boolean('complete');
                $table->jsonb('assertions');
                $table->jsonb('runtime');
                $table->timestampTz('created_at');
            });
            $database->statement(<<<'SQL'
                ALTER TABLE lab_results ADD CONSTRAINT lab_results_terminal CHECK (
                    state IN ('passed','failed','error','timed_out')
                    AND complete = (state IN ('passed','failed'))
                    AND jsonb_typeof(assertions) = 'array'
                    AND jsonb_typeof(runtime) = 'object'
                    AND (NOT complete OR jsonb_array_length(assertions) > 0)
                    AND octet_length(assertions::text) + octet_length(runtime::text) <= 60000
                )
                SQL);
            $database->unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION b33_guard_definition() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF TG_OP = 'DELETE' OR (to_jsonb(NEW) - 'enabled') IS DISTINCT FROM (to_jsonb(OLD) - 'enabled') THEN
                        RAISE EXCEPTION 'lab_definition_release_immutable' USING ERRCODE = '23514';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER lab_definitions_preserve_release BEFORE UPDATE OR DELETE ON lab_definitions
                    FOR EACH ROW EXECUTE FUNCTION b33_guard_definition();

                CREATE OR REPLACE FUNCTION b33_guard_run() RETURNS trigger LANGUAGE plpgsql AS $$
                DECLARE definition lab_definitions%ROWTYPE;
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        RAISE EXCEPTION 'lab_run_history_immutable' USING ERRCODE = '23514';
                    END IF;
                    IF TG_OP = 'UPDATE' THEN
                        IF OLD.state IN ('passed','failed','error','timed_out') AND NEW IS DISTINCT FROM OLD THEN
                            RAISE EXCEPTION 'lab_run_terminal_immutable' USING ERRCODE = '23514';
                        END IF;
                        IF (to_jsonb(NEW) - ARRAY['state','started_at','finished_at','duration_ms'])
                            IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['state','started_at','finished_at','duration_ms']) THEN
                            RAISE EXCEPTION 'lab_run_snapshot_immutable' USING ERRCODE = '23514';
                        END IF;
                        IF (OLD.state = 'queued' AND NEW.state NOT IN ('queued','running','error','timed_out'))
                            OR (OLD.state = 'running' AND NEW.state = 'queued') THEN
                            RAISE EXCEPTION 'lab_run_transition_forbidden' USING ERRCODE = '23514';
                        END IF;
                        IF OLD.started_at IS NOT NULL AND NEW.started_at IS DISTINCT FROM OLD.started_at THEN
                            RAISE EXCEPTION 'lab_run_start_immutable' USING ERRCODE = '23514';
                        END IF;
                        IF NEW.state IN ('passed','failed') AND NOT EXISTS (
                            SELECT 1 FROM lab_results WHERE run_id = NEW.id AND state = NEW.state AND complete
                        ) THEN
                            RAISE EXCEPTION 'lab_run_observation_required' USING ERRCODE = '23514';
                        END IF;
                        IF NEW.state IS DISTINCT FROM OLD.state AND EXISTS (
                            SELECT 1 FROM lab_results WHERE run_id = NEW.id AND state <> NEW.state
                        ) THEN
                            RAISE EXCEPTION 'lab_run_result_state_mismatch' USING ERRCODE = '23514';
                        END IF;
                    ELSE
                        IF NEW.state <> 'queued' THEN
                            RAISE EXCEPTION 'lab_run_must_start_queued' USING ERRCODE = '23514';
                        END IF;
                        SELECT * INTO definition FROM lab_definitions WHERE id = NEW.definition_id FOR KEY SHARE;
                        IF NOT FOUND OR NEW.version_id <> definition.version_id OR NEW.release <> definition.release
                            OR NEW.module_version <> definition.module_version OR NEW.source_commit <> definition.source_commit
                            OR NEW.code_digest <> definition.code_digest OR NEW.suite_digest <> definition.suite_digest
                            OR NEW.version_digest <> definition.version_digest THEN
                            RAISE EXCEPTION 'lab_run_definition_snapshot_mismatch' USING ERRCODE = '23514';
                        END IF;
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER lab_runs_preserve_snapshot BEFORE INSERT OR UPDATE OR DELETE ON lab_runs
                    FOR EACH ROW EXECUTE FUNCTION b33_guard_run();

                CREATE OR REPLACE FUNCTION b33_guard_result() RETURNS trigger LANGUAGE plpgsql AS $$
                DECLARE
                    current_run lab_runs%ROWTYPE;
                    assertion jsonb;
                    assertion_keys text[] := ARRAY[]::text[];
                    has_failure boolean := false;
                BEGIN
                    IF TG_OP = 'INSERT' THEN
                        SELECT * INTO current_run FROM lab_runs WHERE id = NEW.run_id FOR UPDATE;
                        IF NOT FOUND OR (NEW.complete AND current_run.state <> 'running')
                            OR (current_run.state IN ('passed','failed','error','timed_out') AND NEW.state <> current_run.state)
                            OR NEW.created_at < current_run.created_at THEN
                            RAISE EXCEPTION 'lab_result_execution_required' USING ERRCODE = '23514';
                        END IF;
                        IF jsonb_typeof(NEW.assertions) IS DISTINCT FROM 'array' THEN
                            RAISE EXCEPTION 'lab_result_assertions_invalid' USING ERRCODE = '23514';
                        END IF;
                        FOR assertion IN SELECT value FROM jsonb_array_elements(NEW.assertions) LOOP
                            IF jsonb_typeof(assertion) IS DISTINCT FROM 'object'
                                OR NOT (assertion ?& ARRAY['key','passed','expected','observed'])
                                OR (assertion - ARRAY['key','passed','expected','observed']) <> '{}'::jsonb
                                OR jsonb_typeof(assertion->'key') IS DISTINCT FROM 'string'
                                OR (assertion->>'key') !~ '^[a-z0-9][a-z0-9._-]{0,63}$'
                                OR jsonb_typeof(assertion->'passed') IS DISTINCT FROM 'boolean'
                                OR (assertion->>'key') = ANY(assertion_keys) THEN
                                RAISE EXCEPTION 'lab_result_assertions_invalid' USING ERRCODE = '23514';
                            END IF;
                            assertion_keys := array_append(assertion_keys, assertion->>'key');
                            has_failure := has_failure OR NOT (assertion->>'passed')::boolean;
                        END LOOP;
                        IF (NEW.state = 'passed' AND has_failure) OR (NEW.state = 'failed' AND NOT has_failure) THEN
                            RAISE EXCEPTION 'lab_result_outcome_inconsistent' USING ERRCODE = '23514';
                        END IF;
                        RETURN NEW;
                    END IF;
                    RAISE EXCEPTION 'lab_result_immutable' USING ERRCODE = '23514';
                END;
                $$;
                CREATE TRIGGER lab_results_preserve_evidence BEFORE INSERT OR UPDATE OR DELETE ON lab_results
                    FOR EACH ROW EXECUTE FUNCTION b33_guard_result();
                SQL);
        });
    }

    public function down(): void
    {
        DB::connection('pgsql')->transaction(function (): void {
            $database = DB::connection('pgsql');
            $tables = ['lab_definitions', 'lab_runs', 'lab_results'];
            $present = array_filter($tables, static fn (string $table): bool => Schema::connection('pgsql')->hasTable($table));
            if ($present !== [] && count($present) !== 3) {
                throw new RuntimeException('Retour B33 refusé : schéma de contrôle partiellement absent.');
            }
            if ($present !== []) {
                $database->statement('LOCK TABLE lab_definitions, lab_runs, lab_results IN ACCESS EXCLUSIVE MODE');
                foreach ($tables as $table) {
                    if ($database->table($table)->exists()) {
                        throw new RuntimeException('Retour B33 refusé : registre et historique de laboratoire connus à conserver.');
                    }
                }
                foreach (['lab_results', 'lab_runs', 'lab_definitions'] as $table) {
                    Schema::connection('pgsql')->drop($table);
                }
            }
            foreach (['b33_guard_result', 'b33_guard_run', 'b33_guard_definition'] as $function) {
                $database->statement("DROP FUNCTION IF EXISTS {$function}()");
            }
        });
    }
};

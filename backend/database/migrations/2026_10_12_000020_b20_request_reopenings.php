<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resolutions', function (Blueprint $table): void {
            $table->unique(['request_id', 'id'], 'resolutions_request_id_id_unique');
        });
        Schema::create('request_reopenings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained('help_requests')->restrictOnDelete();
            $table->uuid('resolution_id')->unique();
            $table->foreignUuid('reopened_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestampTz('reopened_at');
            $table->integer('request_version_before');
            $table->integer('request_version_after');
            $table->unique(['request_id', 'request_version_after']);
            $table->index(['request_id', 'reopened_at']);
        });
        DB::statement('ALTER TABLE request_reopenings ADD CONSTRAINT request_reopenings_resolution_binding FOREIGN KEY (request_id, resolution_id) REFERENCES resolutions (request_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE request_reopenings ADD CONSTRAINT request_reopenings_versions CHECK (request_version_before BETWEEN 1 AND 2147483646 AND request_version_after::bigint = request_version_before::bigint + 1)');
        DB::statement('ALTER TABLE request_reopenings ADD CONSTRAINT request_reopenings_reason_size CHECK (char_length(reason) BETWEEN 20 AND 4000)');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION b20_guard_request_reopening() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE source resolutions%ROWTYPE; parent help_requests%ROWTYPE;
BEGIN
    IF TG_OP <> 'INSERT' THEN
        RAISE EXCEPTION 'request_reopenings_history_immutable' USING ERRCODE = '23514';
    END IF;
    SELECT * INTO parent FROM help_requests WHERE id = NEW.request_id FOR UPDATE;
    SELECT * INTO source FROM resolutions WHERE id = NEW.resolution_id FOR UPDATE;
    IF source.id IS NULL OR source.request_id <> NEW.request_id
        OR source.revoked_at IS NULL OR source.revoked_at <> NEW.reopened_at
        OR source.accepted_at > NEW.reopened_at OR source.accepted_by <> NEW.reopened_by
        OR parent.author_id <> NEW.reopened_by OR parent.state <> 'open'
        OR parent.lock_version <> NEW.request_version_after THEN
        RAISE EXCEPTION 'request_reopenings_transition_binding' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END
$$;
CREATE TRIGGER b20_request_reopening_guard BEFORE INSERT OR UPDATE OR DELETE ON request_reopenings
    FOR EACH ROW EXECUTE FUNCTION b20_guard_request_reopening();
CREATE OR REPLACE FUNCTION b20_guard_reopened_resolution() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM request_reopenings WHERE resolution_id = OLD.id) THEN
        IF TG_OP = 'DELETE' THEN
            RAISE EXCEPTION 'resolutions_reopened_history_immutable' USING ERRCODE = '23514';
        END IF;
        IF ROW(NEW.id, NEW.request_id, NEW.proposal_id, NEW.accepted_by, NEW.validation_note,
            NEW.environment, NEW.proposal_version, NEW.accepted_at, NEW.revoked_at, NEW.created_at)
            IS DISTINCT FROM ROW(OLD.id, OLD.request_id, OLD.proposal_id, OLD.accepted_by, OLD.validation_note,
                OLD.environment, OLD.proposal_version, OLD.accepted_at, OLD.revoked_at, OLD.created_at) THEN
            RAISE EXCEPTION 'resolutions_reopened_history_immutable' USING ERRCODE = '23514';
        END IF;
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END
$$;
CREATE TRIGGER b20_reopened_resolution_guard BEFORE UPDATE OR DELETE ON resolutions
    FOR EACH ROW EXECUTE FUNCTION b20_guard_reopened_resolution();
SQL);
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::statement('LOCK TABLE resolutions, request_reopenings IN ACCESS EXCLUSIVE MODE');
            if (DB::table('request_reopenings')->exists()) {
                throw new RuntimeException('Retour B20 refusé : historique des réouvertures à conserver.');
            }
            DB::statement('DROP TRIGGER b20_reopened_resolution_guard ON resolutions');
            DB::statement('DROP FUNCTION b20_guard_reopened_resolution()');
            Schema::drop('request_reopenings');
            DB::statement('DROP FUNCTION b20_guard_request_reopening()');
            Schema::table('resolutions', function (Blueprint $table): void {
                $table->dropUnique('resolutions_request_id_id_unique');
            });
        });
    }
};

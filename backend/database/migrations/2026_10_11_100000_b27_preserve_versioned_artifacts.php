<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artifacts', function (Blueprint $table): void {
            // Legacy notices have no invented digest; a new registered version is required.
            $table->char('notices_sha256', 64)->nullable();
            $table->unsignedBigInteger('notices_size')->nullable();
        });
        DB::statement("ALTER TABLE artifacts ADD CONSTRAINT artifacts_notices_integrity CHECK ((notices_sha256 IS NULL AND notices_size IS NULL) OR (notices_path IS NOT NULL AND notices_sha256 IS NOT NULL AND notices_sha256 ~ '^[0-9a-f]{64}$' AND notices_size IS NOT NULL AND notices_size > 0))");
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION b27_guard_artifact() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE old_version uuid; new_version uuid; frozen boolean;
            BEGIN
                IF TG_OP <> 'INSERT' THEN old_version := OLD.version_id; END IF;
                IF TG_OP <> 'DELETE' THEN new_version := NEW.version_id; END IF;
                PERFORM id FROM capsule_versions WHERE id IN (old_version, new_version) ORDER BY id FOR UPDATE;
                SELECT EXISTS (SELECT 1 FROM capsule_versions WHERE id IN (old_version, new_version) AND published_at IS NOT NULL) INTO frozen;
                IF frozen THEN
                    IF TG_OP <> 'UPDATE' THEN
                        RAISE EXCEPTION 'artifacts_published_immutable' USING ERRCODE = '23514';
                    END IF;
                    IF ROW(NEW.id, NEW.version_id, NEW.private_path, NEW.sha256, NEW.size, NEW.notices_path, NEW.notices_sha256, NEW.notices_size, NEW.created_at)
                        IS DISTINCT FROM ROW(OLD.id, OLD.version_id, OLD.private_path, OLD.sha256, OLD.size, OLD.notices_path, OLD.notices_sha256, OLD.notices_size, OLD.created_at) THEN
                        RAISE EXCEPTION 'artifacts_published_immutable' USING ERRCODE = '23514';
                    END IF;
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER b27_artifact_guard BEFORE INSERT OR UPDATE OR DELETE ON artifacts
                FOR EACH ROW EXECUTE FUNCTION b27_guard_artifact();
            SQL);
    }

    public function down(): void
    {
        if (! Schema::hasTable('artifacts')) {
            DB::statement('DROP FUNCTION IF EXISTS b27_guard_artifact()');

            return;
        }
        DB::transaction(function (): void {
            DB::statement('LOCK TABLE capsules, capsule_versions, artifacts IN ACCESS EXCLUSIVE MODE');
            if (DB::table('artifacts')->whereNotNull('notices_sha256')->exists()
                || DB::table('artifacts')->join('capsule_versions', 'capsule_versions.id', '=', 'artifacts.version_id')
                    ->whereNotNull('capsule_versions.published_at')->exists()) {
                throw new RuntimeException('Rollback B27 refuse : les kits versionnes et leurs empreintes doivent etre preserves.');
            }
            DB::statement('DROP TRIGGER IF EXISTS b27_artifact_guard ON artifacts');
            DB::statement('DROP FUNCTION IF EXISTS b27_guard_artifact()');
            DB::statement('ALTER TABLE artifacts DROP CONSTRAINT artifacts_notices_integrity');
            Schema::table('artifacts', function (Blueprint $table): void {
                $table->dropColumn(['notices_sha256', 'notices_size']);
            });
        });
    }
};

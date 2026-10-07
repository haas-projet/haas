<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE capsule_versions DROP CONSTRAINT capsule_versions_published_at_requires_state');
        DB::statement("ALTER TABLE capsule_versions ADD CONSTRAINT capsule_versions_published_at_requires_state CHECK ((state IN ('published', 'withdrawn') AND published_at IS NOT NULL AND reviewer_id IS NOT NULL) OR (state NOT IN ('published', 'withdrawn') AND published_at IS NULL))");
        DB::statement('ALTER TABLE capsule_versions ADD CONSTRAINT capsule_versions_lock_positive CHECK (lock_version > 0)');
        Schema::table('capsule_versions', function (Blueprint $table): void {
            $table->dropForeign(['reviewer_id']);
            $table->foreign('reviewer_id')->references('id')->on('users')->restrictOnDelete();
        });

        // Les contraintes protègent aussi les écritures SQL hors des futurs Services B24/B25.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION b22_guard_capsule_version() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE capsule_owner uuid;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.published_at IS NOT NULL THEN
                        RAISE EXCEPTION 'capsule_versions_published_immutable' USING ERRCODE = '23514';
                    END IF;
                    RETURN OLD;
                END IF;
                IF TG_OP = 'UPDATE' AND OLD.published_at IS NOT NULL THEN
                    IF ROW(NEW.id, NEW.capsule_id, NEW.version_label, NEW.body, NEW.limits, NEW.reviewer_id, NEW.published_at, NEW.created_at)
                        IS DISTINCT FROM ROW(OLD.id, OLD.capsule_id, OLD.version_label, OLD.body, OLD.limits, OLD.reviewer_id, OLD.published_at, OLD.created_at)
                        OR (OLD.state = 'withdrawn' AND NEW.state <> 'withdrawn')
                        OR (OLD.state = 'published' AND NEW.state NOT IN ('published', 'withdrawn')) THEN
                        RAISE EXCEPTION 'capsule_versions_published_immutable' USING ERRCODE = '23514';
                    END IF;
                END IF;
                IF NEW.state IN ('published', 'withdrawn') THEN
                    SELECT owner_id INTO capsule_owner FROM capsules WHERE id = NEW.capsule_id FOR UPDATE;
                    IF NEW.reviewer_id = capsule_owner OR EXISTS (
                        SELECT 1 FROM capsule_contributors WHERE version_id = NEW.id AND user_id = NEW.reviewer_id
                    ) THEN
                        RAISE EXCEPTION 'capsule_versions_independent_reviewer' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER b22_capsule_version_guard BEFORE INSERT OR UPDATE OR DELETE ON capsule_versions
                FOR EACH ROW EXECUTE FUNCTION b22_guard_capsule_version();

            CREATE OR REPLACE FUNCTION b22_guard_capsule_provenance() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF ROW(NEW.id, NEW.owner_id, NEW.source_request_id, NEW.editorial_origin, NEW.slug)
                    IS DISTINCT FROM ROW(OLD.id, OLD.owner_id, OLD.source_request_id, OLD.editorial_origin, OLD.slug)
                    AND EXISTS (SELECT 1 FROM capsule_versions WHERE capsule_id = OLD.id AND published_at IS NOT NULL) THEN
                    RAISE EXCEPTION 'capsules_published_provenance_immutable' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER b22_capsule_provenance_guard BEFORE UPDATE ON capsules
                FOR EACH ROW EXECUTE FUNCTION b22_guard_capsule_provenance();

            CREATE OR REPLACE FUNCTION b22_guard_capsule_attachment() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE old_version uuid; new_version uuid; frozen boolean;
            BEGIN
                IF TG_OP <> 'INSERT' THEN old_version := OLD.version_id; END IF;
                IF TG_OP <> 'DELETE' THEN new_version := NEW.version_id; END IF;
                -- Même ordre pour les deux versions lorsqu'une écriture tente de déplacer un lien.
                PERFORM id FROM capsule_versions WHERE id IN (old_version, new_version) ORDER BY id FOR UPDATE;
                SELECT EXISTS (
                    SELECT 1 FROM capsule_versions WHERE id IN (old_version, new_version) AND published_at IS NOT NULL
                ) INTO frozen;
                IF frozen THEN
                    RAISE EXCEPTION 'capsule_versions_published_attachments_immutable' USING ERRCODE = '23514';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER b22_capsule_technology_guard BEFORE INSERT OR UPDATE OR DELETE ON capsule_version_technologies
                FOR EACH ROW EXECUTE FUNCTION b22_guard_capsule_attachment();
            CREATE TRIGGER b22_capsule_contributor_guard BEFORE INSERT OR UPDATE OR DELETE ON capsule_contributors
                FOR EACH ROW EXECUTE FUNCTION b22_guard_capsule_attachment();
            SQL);
    }

    public function down(): void
    {
        // L'ancien CHECK ne peut représenter un retrait qui conserve sa date de publication.
        if (Schema::hasTable('capsule_versions') && DB::table('capsule_versions')->where('state', 'withdrawn')->exists()) {
            throw new RuntimeException('Rollback B22 refusé : des versions retirées doivent conserver leur historique.');
        }
        foreach ([
            'capsule_contributors' => 'b22_capsule_contributor_guard',
            'capsule_version_technologies' => 'b22_capsule_technology_guard',
            'capsules' => 'b22_capsule_provenance_guard',
            'capsule_versions' => 'b22_capsule_version_guard',
        ] as $table => $trigger) {
            if (Schema::hasTable($table)) {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON {$table}");
            }
        }
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS b22_guard_capsule_attachment();
            DROP FUNCTION IF EXISTS b22_guard_capsule_provenance();
            DROP FUNCTION IF EXISTS b22_guard_capsule_version();
            SQL);
        if (! Schema::hasTable('capsule_versions')) {
            return;
        }
        Schema::table('capsule_versions', function (Blueprint $table): void {
            $table->dropForeign(['reviewer_id']);
            $table->foreign('reviewer_id')->references('id')->on('users')->nullOnDelete();
        });
        DB::statement('ALTER TABLE capsule_versions DROP CONSTRAINT capsule_versions_lock_positive');
        DB::statement('ALTER TABLE capsule_versions DROP CONSTRAINT capsule_versions_published_at_requires_state');
        DB::statement("ALTER TABLE capsule_versions ADD CONSTRAINT capsule_versions_published_at_requires_state CHECK (published_at IS NULL OR state = 'published')");
    }
};

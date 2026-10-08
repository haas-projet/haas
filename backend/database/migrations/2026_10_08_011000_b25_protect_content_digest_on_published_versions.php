<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B25 — Étendre la garde SQL de B22 à `content_digest`.
 *
 * `b22_guard_capsule_version` refuse déjà la mutation de `version_label`,
 * `body`, `limits`, `capsule_id` dès que `published_at` est posé. Ce lot
 * ajoute `content_digest` à la liste pour que la ligne reste figée en base
 * aussi longtemps qu'elle est publiée ou retirée. La transition inverse
 * (`CREATE OR REPLACE`) remet exactement l'état B22.
 */
return new class extends Migration
{
    public function up(): void
    {
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
                    IF ROW(NEW.id, NEW.capsule_id, NEW.version_label, NEW.body, NEW.limits, NEW.content_digest, NEW.reviewer_id, NEW.published_at, NEW.created_at)
                        IS DISTINCT FROM ROW(OLD.id, OLD.capsule_id, OLD.version_label, OLD.body, OLD.limits, OLD.content_digest, OLD.reviewer_id, OLD.published_at, OLD.created_at)
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
            SQL);
    }

    public function down(): void
    {
        if (! Schema::hasTable('capsule_versions')) {
            return;
        }
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
            SQL);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B25 — Empreinte figée au moment de la publication.
 *
 * Décision du propriétaire du domaine : le cahier §09 et §13 citent une
 * « empreinte de l'artefact lorsqu'il existe » mais ne nomme pas la
 * colonne qui l'archive côté version. Lecture restrictive consignée :
 * `content_digest` est nullable, sans index unique (une collision de corps
 * entre capsules distinctes reste légitime) et n'est JAMAIS écrit hors du
 * Service de publication.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capsule_versions', function (Blueprint $table): void {
            $table->char('content_digest', 64)->nullable()->after('limits');
        });
        DB::statement("ALTER TABLE capsule_versions ADD CONSTRAINT capsule_versions_content_digest_format CHECK (content_digest IS NULL OR content_digest ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE capsule_versions ADD CONSTRAINT capsule_versions_content_digest_requires_published CHECK (content_digest IS NULL OR state IN ('published', 'withdrawn'))");
    }

    public function down(): void
    {
        if (! Schema::hasTable('capsule_versions') || ! Schema::hasColumn('capsule_versions', 'content_digest')) {
            return;
        }
        DB::statement('ALTER TABLE capsule_versions DROP CONSTRAINT IF EXISTS capsule_versions_content_digest_requires_published');
        DB::statement('ALTER TABLE capsule_versions DROP CONSTRAINT IF EXISTS capsule_versions_content_digest_format');
        Schema::table('capsule_versions', function (Blueprint $table): void {
            $table->dropColumn('content_digest');
        });
    }
};

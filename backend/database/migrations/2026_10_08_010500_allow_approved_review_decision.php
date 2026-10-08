<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B25 — Enregistrer la décision « approved » dans l'historique des revues.
 *
 * La table `capsule_version_reviews` (B24) n'acceptait que `request_changes`.
 * La publication livre une seconde décision, `approved`, qui garde la même
 * ligne d'audit mais sans note obligatoire (le motif est documentaire :
 * la publication n'exige pas de prose contradictoire).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE capsule_version_reviews DROP CONSTRAINT IF EXISTS capsule_version_reviews_decision_check");
        DB::statement("ALTER TABLE capsule_version_reviews ADD CONSTRAINT capsule_version_reviews_decision_check CHECK (decision IN ('request_changes', 'approved'))");
        DB::statement('ALTER TABLE capsule_version_reviews ALTER COLUMN note DROP NOT NULL');
        DB::statement('ALTER TABLE capsule_version_reviews DROP CONSTRAINT capsule_version_reviews_note_min');
        DB::statement("ALTER TABLE capsule_version_reviews ADD CONSTRAINT capsule_version_reviews_note_min CHECK ((decision = 'approved' AND note IS NULL) OR (decision <> 'approved' AND note IS NOT NULL AND char_length(btrim(note)) BETWEEN 20 AND 2000))");
    }

    public function down(): void
    {
        if (! Schema::hasTable('capsule_version_reviews')) {
            return;
        }
        if (DB::table('capsule_version_reviews')->where('decision', 'approved')->exists()) {
            throw new RuntimeException('Retour B25 refusé : décisions de publication à conserver.');
        }
        DB::statement('ALTER TABLE capsule_version_reviews DROP CONSTRAINT capsule_version_reviews_note_min');
        DB::statement("ALTER TABLE capsule_version_reviews ADD CONSTRAINT capsule_version_reviews_note_min CHECK (char_length(btrim(note)) BETWEEN 20 AND 2000)");
        DB::statement('ALTER TABLE capsule_version_reviews ALTER COLUMN note SET NOT NULL');
        DB::statement('ALTER TABLE capsule_version_reviews DROP CONSTRAINT capsule_version_reviews_decision_check');
        DB::statement("ALTER TABLE capsule_version_reviews ADD CONSTRAINT capsule_version_reviews_decision_check CHECK (decision = 'request_changes')");
    }
};

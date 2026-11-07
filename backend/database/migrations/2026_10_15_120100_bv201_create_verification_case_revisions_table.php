<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BV201 — révisions de cas de vérification.
// Une révision est datée (submitted_at) seulement une fois soumise. Le digest
// SHA-256 est calculé sur un JSON canonique du corps (même approche que
// content_digest en B25, Services/Capsules/PublishCapsuleVersionService.php:42).
// Unicité (case_id, revision_no). La table reste immuable au niveau applicatif
// une fois intégrée : les triggers métier arrivent en BV203 avec case_scenario_links.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_case_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('case_id')->constrained('verification_cases')->restrictOnDelete();
            $table->unsignedInteger('revision_no');
            $table->jsonb('body');
            $table->char('digest', 64);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['case_id', 'revision_no'], 'verification_case_revisions_case_revno_unique');
            $table->index(['case_id', 'submitted_at']);
        });

        DB::statement("ALTER TABLE verification_case_revisions ADD CONSTRAINT verification_case_revisions_digest_shape CHECK (digest ~ '^[0-9a-f]{64}$')");
        DB::statement('ALTER TABLE verification_case_revisions ADD CONSTRAINT verification_case_revisions_revision_no_positive CHECK (revision_no >= 1)');
        DB::statement("ALTER TABLE verification_case_revisions ADD CONSTRAINT verification_case_revisions_body_object CHECK (jsonb_typeof(body) = 'object')");
        DB::statement('ALTER TABLE verification_case_revisions ADD CONSTRAINT verification_case_revisions_submitted_after_created CHECK (submitted_at IS NULL OR submitted_at >= created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_case_revisions');
    }
};

<?php

use App\Enums\Capsules\ReuseReportOutcome;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B28 — Historique append-only des retours de réutilisation
 * (CAHIER_DES_CHARGES.md:502 « modification possible avec historique »).
 * Décision du propriétaire du domaine : la table n'est pas nommée par le
 * dictionnaire — convention `reuse_report_revisions`, par analogie avec
 * `capsule_version_reviews`. Pas de modification/suppression côté Service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reuse_report_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('reuse_reports')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('environment', 200);
            $table->date('reported_at');
            $table->enum('outcome', array_map(fn (ReuseReportOutcome $o) => $o->value, ReuseReportOutcome::cases()));
            $table->string('observation', 2000);
            $table->boolean('is_internal');
            $table->timestampTz('revised_at');

            $table->unique(['report_id', 'revision'], 'reuse_report_revisions_report_revision_unique');
        });
        DB::statement('ALTER TABLE reuse_report_revisions ADD CONSTRAINT reuse_report_revisions_revision_positive CHECK (revision >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reuse_report_revisions');
    }
};

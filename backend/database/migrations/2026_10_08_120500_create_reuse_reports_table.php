<?php

use App\Enums\Capsules\ReuseReportOutcome;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B28 — Retour de réutilisation unique par (membre, version)
 * (CAHIER_DES_CHARGES.md:875 et :500-502). `is_internal` est renseigné
 * par le serveur (owner ou contributeur de la version). `revision`
 * garde la trace du nombre de mises à jour. Les anciens états sont
 * archivés dans `reuse_report_revisions`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reuse_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('version_id')->constrained('capsule_versions')->cascadeOnDelete();
            $table->string('environment', 200);
            $table->date('reported_at');
            $table->enum('outcome', array_map(fn (ReuseReportOutcome $o) => $o->value, ReuseReportOutcome::cases()));
            $table->string('observation', 2000);
            $table->boolean('is_internal')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->timestampsTz();

            $table->unique(['user_id', 'version_id'], 'reuse_reports_user_version_unique');
        });
        DB::statement('ALTER TABLE reuse_reports ADD CONSTRAINT reuse_reports_environment_not_blank CHECK (char_length(btrim(environment)) BETWEEN 1 AND 200)');
        DB::statement('ALTER TABLE reuse_reports ADD CONSTRAINT reuse_reports_observation_not_blank CHECK (char_length(btrim(observation)) BETWEEN 20 AND 2000)');
        DB::statement('ALTER TABLE reuse_reports ADD CONSTRAINT reuse_reports_revision_positive CHECK (revision >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reuse_reports');
    }
};

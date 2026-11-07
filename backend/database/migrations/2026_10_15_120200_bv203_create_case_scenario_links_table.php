<?php

use App\Enums\Lab\LabScenario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BV203 — table case_scenario_links.
// Lie une révision de cas intégrée à un scénario approuvé (LabScenario, B33).
// Clé par révision ; une révision intégrée reste figée avec son lien, une
// nouvelle révision soumise après intégration ne touche pas l'ancien lien.
// `reviewer_id` : relecteur habilité (≠ auteur du cas), via la commande
// d'intégration lab:bind-case (BV203). Aucune FK vers un enum ou un registre
// de scénarios : la liste autorisée est contrôlée en CHECK.
return new class extends Migration
{
    public function up(): void
    {
        $allowed = implode(',', array_map(static fn (LabScenario $s): string => "'".$s->value."'", LabScenario::cases()));

        Schema::create('case_scenario_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('case_revision_id')->unique()->constrained('verification_case_revisions')->restrictOnDelete();
            $table->string('scenario_key', 32);
            $table->char('suite_digest', 64);
            $table->char('release_commit', 40);
            $table->foreignUuid('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('integrated_at');
            $table->timestampsTz();

            $table->index(['scenario_key', 'integrated_at']);
            $table->index(['reviewer_id', 'integrated_at']);
        });

        DB::statement("ALTER TABLE case_scenario_links ADD CONSTRAINT case_scenario_links_scenario_values CHECK (scenario_key IN ({$allowed}))");
        DB::statement("ALTER TABLE case_scenario_links ADD CONSTRAINT case_scenario_links_suite_digest_shape CHECK (suite_digest ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE case_scenario_links ADD CONSTRAINT case_scenario_links_release_commit_shape CHECK (release_commit ~ '^[0-9a-f]{40}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('case_scenario_links');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Table proposals — colonnes citées littéralement dans
// docs/product/CAHIER_DES_CHARGES.md:828.
// États : docs/architecture/ARCHITECTURE.md:256. Défaut 'proposed' aligné sur
// l'ordre d'énumération.
// Longueurs 20–4 000 : docs/product/CAHIER_DES_CHARGES.md:418, repris dans
// docs/execution/tasks.json:224 et docs/execution/PLAN_COMMITS.md:203.
// Colonne `code` : exigence citée (code facultatif 12 000 — CAHIER:418,
// tasks.json:224) mais aucun nom de colonne SQL cité dans le dépôt ; non codée
// en B11, à confirmer avant le lot consommateur.
// UNIQUE (request_id, id) : prérequis documenté pour la FK composite
// de resolutions, qui vérifie « l'appartenance proposition/demande »
// (docs/execution/tasks.json:147, docs/execution/PLAN_COMMITS.md:133).
// ON DELETE : RESTRICT (aucune citation cascade ; CAHIER_DES_CHARGES.md:394
// confirme que les suppressions destructrices sont réservées à la modération).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained('help_requests')->restrictOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $table->text('diagnosis');
            $table->text('fix');
            $table->text('verification');
            $table->text('limits');
            $table->string('state', 32)->default('proposed');
            $table->timestampsTz();

            $table->unique(['request_id', 'id'], 'proposals_request_id_id_unique');
            $table->index(['request_id', 'created_at']);
        });

        DB::statement('ALTER TABLE proposals ADD CONSTRAINT proposals_diagnosis_length CHECK (char_length(btrim(diagnosis)) BETWEEN 20 AND 4000)');
        DB::statement('ALTER TABLE proposals ADD CONSTRAINT proposals_fix_length CHECK (char_length(btrim(fix)) BETWEEN 20 AND 4000)');
        DB::statement('ALTER TABLE proposals ADD CONSTRAINT proposals_verification_length CHECK (char_length(btrim(verification)) BETWEEN 20 AND 4000)');
        DB::statement('ALTER TABLE proposals ADD CONSTRAINT proposals_limits_length CHECK (char_length(btrim(limits)) BETWEEN 20 AND 4000)');
        DB::statement("ALTER TABLE proposals ADD CONSTRAINT proposals_state_values CHECK (state IN ('proposed', 'accepted', 'not_selected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};

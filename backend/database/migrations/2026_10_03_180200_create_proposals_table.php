<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Table proposals — colonnes citées littéralement dans
// docs/product/CAHIER_DES_CHARGES.md:828.
// États : docs/architecture/ARCHITECTURE.md:256. Aucun défaut en base :
// aucune citation littérale ne fixe la valeur initiale ; les Services B18+
// posent l'état explicitement.
// Longueurs CHECK retirées : les règles 20-4000 (CAHIER:418) sont portées
// par la FormRequest B18+ ; aucun CHECK en base.
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
            $table->string('state', 32);
            $table->timestampsTz();

            $table->unique(['request_id', 'id'], 'proposals_request_id_id_unique');
            $table->index(['request_id', 'created_at']);
        });

        DB::statement("ALTER TABLE proposals ADD CONSTRAINT proposals_state_values CHECK (state IN ('proposed', 'accepted', 'not_selected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};

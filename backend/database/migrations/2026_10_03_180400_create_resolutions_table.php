<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Table resolutions — colonnes citées littéralement dans
// docs/product/CAHIER_DES_CHARGES.md:829.
// Index unique partiel « une seule résolution active par demande » :
// docs/architecture/ARCHITECTURE.md:316-320 ; CAHIER_DES_CHARGES.md:837 ;
// docs/execution/tasks.json:147 ; docs/execution/PLAN_COMMITS.md:133.
// FK composite (request_id, proposal_id) → proposals(request_id, id) :
// contrainte d'appartenance proposition/demande demandée par
// docs/execution/tasks.json:147 et docs/execution/PLAN_COMMITS.md:133.
// Un UUID valide ne suffit pas, voir ARCHITECTURE.md:308.
// ON DELETE : RESTRICT (aucune citation cascade).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resolutions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->uuid('proposal_id');
            $table->foreignUuid('accepted_by')->constrained('users')->restrictOnDelete();
            $table->text('validation_note');
            $table->timestampTz('accepted_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->foreign('request_id')->references('id')->on('help_requests')->restrictOnDelete();
            $table->index(['request_id', 'created_at']);
        });

        // FK composite : la paire (request_id, proposal_id) doit référencer une
        // proposition appartenant à la même demande (proposals a UNIQUE
        // (request_id, id)). Posée en SQL brut car Blueprint::foreign ne tape
        // pas les clés étrangères composites.
        DB::statement('ALTER TABLE resolutions ADD CONSTRAINT resolutions_proposal_belongs_to_request FOREIGN KEY (request_id, proposal_id) REFERENCES proposals (request_id, id) ON DELETE RESTRICT');

        // Index unique partiel : une seule résolution non révoquée par demande.
        DB::statement('CREATE UNIQUE INDEX resolutions_one_active_per_request ON resolutions (request_id) WHERE revoked_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('resolutions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Table help_requests — colonnes citées littéralement dans
// docs/product/CAHIER_DES_CHARGES.md:825 et longueurs du formulaire
// dans docs/product/CAHIER_DES_CHARGES.md:378-384.
// États : docs/architecture/ARCHITECTURE.md:255. Défaut 'draft' aligné sur
// l'ordre d'énumération ; brouillon décrit dans docs/execution/tasks.json:181.
// `environment` : « Environnement court » (CAHIER_DES_CHARGES.md:384) ; limite
// chiffrée non citée, longueur Laravel par défaut (255).
// `expected`/`attempts` NOT NULL en B11 ; BV201 (ajout help_intent) et
// BC07 (ask_question) les rendront nullables (CAHIER_DES_CHARGES.md:408).
// ON DELETE : RESTRICT (aucune citation cascade ; alignement implicite sur
// backend/database/migrations/2026_10_02_000005_b05_...php pour la FK
// users_technologies.technology_id->users (restrictOnDelete)).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 140);
            $table->text('goal');
            $table->text('expected');
            $table->text('observed');
            $table->text('attempts');
            $table->string('environment');
            $table->string('state', 32)->default('draft');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->index(['state', 'created_at']);
            $table->index(['author_id', 'created_at']);
        });

        DB::statement('ALTER TABLE help_requests ADD CONSTRAINT help_requests_title_length CHECK (char_length(btrim(title)) BETWEEN 15 AND 140)');
        DB::statement('ALTER TABLE help_requests ADD CONSTRAINT help_requests_goal_length CHECK (char_length(btrim(goal)) BETWEEN 30 AND 2000)');
        DB::statement('ALTER TABLE help_requests ADD CONSTRAINT help_requests_expected_length CHECK (char_length(btrim(expected)) BETWEEN 30 AND 2000)');
        DB::statement('ALTER TABLE help_requests ADD CONSTRAINT help_requests_observed_length CHECK (char_length(btrim(observed)) BETWEEN 30 AND 4000)');
        DB::statement('ALTER TABLE help_requests ADD CONSTRAINT help_requests_attempts_length CHECK (char_length(btrim(attempts)) BETWEEN 20 AND 3000)');
        DB::statement("ALTER TABLE help_requests ADD CONSTRAINT help_requests_state_values CHECK (state IN ('draft', 'open', 'in_progress', 'resolved', 'archived'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('help_requests');
    }
};

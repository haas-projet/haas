<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Table help_requests — colonnes citées littéralement dans
// docs/product/CAHIER_DES_CHARGES.md:825.
// États : docs/architecture/ARCHITECTURE.md:255. Aucun défaut en base :
// aucune citation littérale ne fixe la valeur initiale ; les Services B14+
// posent l'état explicitement.
// `environment` : « Environnement court » (CAHIER_DES_CHARGES.md:384) ; limite
// chiffrée non citée, longueur Laravel par défaut (255).
// `expected`/`attempts` NOT NULL en B11 ; BV201 (ajout help_intent) et
// BC07 (ask_question) les rendront nullables (CAHIER_DES_CHARGES.md:408).
// Longueurs CHECK retirées : les règles 15-140 / 30-2000 / 30-4000 / 20-3000
// du formulaire (CAHIER:378-381) sont conditionnelles (`help_intent`
// ask_question, « aucune » explicite autorisé CAHIER:381) et portées par
// la FormRequest B14+. `title` conserve `varchar(140)` strictement citée.
// ON DELETE : RESTRICT par défaut (aucune suppression de demande dans le
// produit, CAHIER_DES_CHARGES.md:394).
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
            $table->string('state', 32);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->index(['state', 'created_at']);
            $table->index(['author_id', 'created_at']);
        });

        DB::statement("ALTER TABLE help_requests ADD CONSTRAINT help_requests_state_values CHECK (state IN ('draft', 'open', 'in_progress', 'resolved', 'archived'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('help_requests');
    }
};

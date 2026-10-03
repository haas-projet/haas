<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Table request_technologies — pivot cité dans
// docs/product/CAHIER_DES_CHARGES.md:826 avec « paire unique ».
// `version_label` : « version texte de 40 caractères maximum par technologie »
// (CAHIER_DES_CHARGES.md:382).
// ON DELETE : RESTRICT par défaut (aucune citation cascade sur ce pivot).
// Pivot aligné sur user_technologies (backend/database/migrations/
// 2026_10_02_000005_b05_...) : clé primaire composite (request_id,
// technology_id), pas de colonne id surrogate.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_technologies', function (Blueprint $table): void {
            $table->foreignUuid('request_id')->constrained('help_requests')->restrictOnDelete();
            $table->foreignUuid('technology_id')->constrained('technologies')->restrictOnDelete();
            $table->string('version_label', 40)->nullable();
            $table->timestampsTz();

            $table->primary(['request_id', 'technology_id']);
            $table->index(['technology_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_technologies');
    }
};

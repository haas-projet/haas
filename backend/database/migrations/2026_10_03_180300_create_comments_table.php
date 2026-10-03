<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Table comments — colonnes citées littéralement dans
// docs/product/CAHIER_DES_CHARGES.md:827 (« édition historisée »).
// Longueur CHECK body retirée : règle 1-4000 (CAHIER:418) portée par la
// FormRequest B17+ ; aucun CHECK en base.
// `edited_at` et `hidden_at` nullable ; l'historique détaillé reste porté
// par content_revisions en B12 (ARCHITECTURE.md:139-147).
// ON DELETE : RESTRICT — « Les suppressions destructrices de discussions
// sont réservées au traitement de modération » (CAHIER_DES_CHARGES.md:394).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained('help_requests')->restrictOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestampTz('edited_at')->nullable();
            $table->timestampTz('hidden_at')->nullable();
            $table->timestampsTz();

            $table->index(['request_id', 'created_at']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};

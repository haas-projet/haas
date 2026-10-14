<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B28 — Favoris privés par (membre, capsule) (CAHIER_DES_CHARGES.md:875).
 * « Un favori est privé et peut être supprimé » (:506). Pas de score,
 * pas de compteur public. ON DELETE : CASCADE sur l'utilisateur et la
 * capsule (un retrait de l'un supprime les attaches privées).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('capsule_id')->constrained('capsules')->cascadeOnDelete();
            $table->timestampTz('created_at');

            $table->unique(['user_id', 'capsule_id'], 'favorites_user_capsule_unique');
        });
        DB::statement('ALTER TABLE favorites ADD CONSTRAINT favorites_created_at_present CHECK (created_at IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};

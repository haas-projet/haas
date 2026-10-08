<?php

use App\Enums\Capsules\ReviewDecision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Note des revues d'une version de capsule. Décision du propriétaire du
 * domaine (CAHIER_DES_CHARGES.md:462 ne nomme pas la table : « Un autre
 * membre habilité vérifie la cohérence et les droits. [...] L'auteur ne
 * valide pas seul sa propre revue éditoriale »).
 *
 * ON DELETE : restrictOnDelete sur reviewer_id (l'historique de revue ne
 * doit pas être amputé par une suppression de compte) ; cascadeOnDelete
 * sur version_id (si la version-brouillon disparaît, les revues suivent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capsule_version_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('version_id')->constrained('capsule_versions')->cascadeOnDelete();
            $table->foreignUuid('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->enum('decision', array_map(fn (ReviewDecision $d) => $d->value, ReviewDecision::cases()));
            $table->string('note', 2000);
            $table->timestampTz('created_at');

            $table->index(['version_id', 'created_at']);
        });

        // Note : au moins 20 caractères non blancs. Les retours à la ligne sont autorisés
        // (une note multilignes reste légitime pour un retour détaillé).
        DB::statement('ALTER TABLE capsule_version_reviews ADD CONSTRAINT capsule_version_reviews_note_min CHECK (char_length(btrim(note)) BETWEEN 20 AND 2000)');
    }

    public function down(): void
    {
        Schema::dropIfExists('capsule_version_reviews');
    }
};

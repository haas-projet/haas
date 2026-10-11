<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pivot capsule_version_technologies.
// Modèle calqué sur `request_technologies` (B11 : CAHIER_DES_CHARGES.md:826)
// et sur `user_technologies` (B05) : clé primaire composite, pas d'UUID.
// `version_label` : versions compatibles déclarées (CAHIER_DES_CHARGES.md:456
// et :382) ; 40 caractères maximum nullable.
// ON DELETE : version_id en CASCADE (si la version-brouillon disparaît, ses
// technologies suivent) ; technology_id en RESTRICT (le référentiel ne doit
// pas être amputé par le retrait d'une capsule).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capsule_version_technologies', function (Blueprint $table): void {
            $table->foreignUuid('version_id')->constrained('capsule_versions')->cascadeOnDelete();
            $table->foreignUuid('technology_id')->constrained('technologies')->restrictOnDelete();
            $table->string('version_label', 40)->nullable();
            $table->timestampsTz();

            $table->primary(['version_id', 'technology_id']);
            $table->index(['technology_id', 'version_id']);
        });

        DB::statement("ALTER TABLE capsule_version_technologies ADD CONSTRAINT capsule_version_technologies_label_format CHECK (version_label IS NULL OR (char_length(version_label) BETWEEN 1 AND 40 AND version_label = btrim(version_label) AND version_label !~ '[[:cntrl:]]'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('capsule_version_technologies');
    }
};

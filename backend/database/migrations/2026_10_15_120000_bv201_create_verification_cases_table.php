<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BV201 — table verification_cases.
// Parent unique : help_request_id XOR capsule_version_id (CAHIER_DES_CHARGES.md:1629).
// États : draft, submitted, reviewed, declined, integrated, withdrawn
// (CAHIER_DES_CHARGES.md:317). RESTRICT par défaut sur les FK : aucun cas
// n'autorise la suppression de son auteur ni de son parent (CAHIER:1629/1685).
// Pas d'enum PostgreSQL : varchar + CHECK comme help_requests/lab_runs.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('help_request_id')->nullable()->constrained('help_requests')->restrictOnDelete();
            $table->foreignUuid('capsule_version_id')->nullable()->constrained('capsule_versions')->restrictOnDelete();
            $table->string('state', 16)->default('draft');
            $table->unsignedInteger('current_revision')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->index(['state', 'created_at']);
            $table->index(['author_id', 'created_at']);
            $table->index(['help_request_id', 'created_at']);
            $table->index(['capsule_version_id', 'created_at']);
        });

        DB::statement("ALTER TABLE verification_cases ADD CONSTRAINT verification_cases_state_values CHECK (state IN ('draft','submitted','reviewed','declined','integrated','withdrawn'))");
        DB::statement('ALTER TABLE verification_cases ADD CONSTRAINT verification_cases_parent_xor CHECK (num_nonnulls(help_request_id, capsule_version_id) = 1)');
        DB::statement('ALTER TABLE verification_cases ADD CONSTRAINT verification_cases_current_revision_consistent CHECK ((state = \'draft\' AND current_revision = 0) OR (state <> \'draft\' AND current_revision >= 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_cases');
    }
};

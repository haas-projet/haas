<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $database = DB::connection('pgsql');
        if ($database->getDriverName() !== 'pgsql') {
            throw new RuntimeException('BV204 exige la connexion applicative PostgreSQL.');
        }
        $database->transaction(function () use ($database): void {
            $schema = Schema::connection('pgsql');
            $schema->create('comparison_profiles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('family_key', 40);
                $table->jsonb('baseline_manifest');
                $table->foreignUuid('candidate_version_id')
                    ->unique()
                    ->constrained('capsule_versions')
                    ->restrictOnDelete();
                $table->char('suite_digest', 64);
                $table->string('input_schema_version', 32);
                $table->boolean('enabled')->default(false);
                $table->timestampTz('created_at');
            });
            // Famille fermée : seul le runner B33 est connu ; aligné avec
            // lab_definitions_runner_fixed (B33 ligne 33).
            $database->statement("ALTER TABLE comparison_profiles ADD CONSTRAINT comparison_profiles_family_fixed CHECK (family_key = 'b1.idempotent.v1')");
            // Digests hexadécimaux. Les digests internes de la baseline et du
            // candidat vivent respectivement dans baseline_manifest et dans
            // capsule_versions.content_digest ; seule la suite est portée ici.
            $database->statement("ALTER TABLE comparison_profiles ADD CONSTRAINT comparison_profiles_suite_digest_shape CHECK (suite_digest ~ '^[0-9a-f]{64}$')");
            $database->statement("ALTER TABLE comparison_profiles ADD CONSTRAINT comparison_profiles_input_schema_shape CHECK (input_schema_version ~ '^[0-9]+[.][0-9]+[.][0-9]+$')");
            // La structure du manifeste est contrôlée par le service d'intégration.
            // Garantie minimale au niveau du schéma : objet JSONB non vide.
            $database->statement("ALTER TABLE comparison_profiles ADD CONSTRAINT comparison_profiles_baseline_manifest_is_object CHECK (jsonb_typeof(baseline_manifest) = 'object' AND baseline_manifest <> '{}'::jsonb)");
        });
    }

    public function down(): void
    {
        // Fidèle : retire la table créée par cette migration. Tolérante : si
        // la table est absente (rollback partiel), ne fait rien. Jamais
        // CASCADE : les enfants (BV205 comparison_runs) sont retirés avant
        // par leur propre down() ou par DomainTables::dropAll().
        if (! Schema::connection('pgsql')->hasTable('comparison_profiles')) {
            return;
        }
        DB::connection('pgsql')->transaction(function (): void {
            Schema::connection('pgsql')->drop('comparison_profiles');
        });
    }
};

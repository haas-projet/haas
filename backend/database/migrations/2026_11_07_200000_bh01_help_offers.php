<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            Schema::table('projects', function (Blueprint $table): void {
                $table->boolean('help_open')->default(false);
                $table->jsonb('help_categories')->nullable();
                $table->index(['help_open', 'publication_state', 'published_at', 'id']);
            });
            Schema::table('profiles', function (Blueprint $table): void {
                $table->jsonb('preferred_help_categories')->nullable();
            });
            Schema::create('help_offers', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignUuid('proposer_id')->constrained('users')->restrictOnDelete();
                $table->string('category', 40);
                $table->text('public_summary');
                $table->text('expected_outcome');
                $table->boolean('allow_public_summary')->default(false);
                $table->timestampTz('allow_public_summary_at')->nullable();
                $table->string('state', 16)->default('pending');
                $table->integer('lock_version')->default(1);
                $table->timestampTz('expires_at');
                $table->timestampTz('decided_at')->nullable();
                $table->timestampTz('accepted_at')->nullable();
                $table->foreignUuid('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignUuid('accepted_request_id')->nullable()->constrained('help_requests')->restrictOnDelete();
                $table->text('declined_reason')->nullable();
                $table->string('closed_reason', 40)->nullable();
                $table->timestampsTz();
                $table->index(['proposer_id', 'state', 'created_at']);
                $table->index(['state', 'expires_at']);
            });
            DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_help_categories_shape CHECK (
                help_categories IS NULL OR (
                    jsonb_typeof(help_categories) = 'array'
                    AND jsonb_array_length(help_categories) BETWEEN 1 AND 5
                    AND help_categories <@ '[\"usability_feedback\", \"code_review\", \"reproduce_behavior\", \"explain_concept\", \"documentation\"]'::jsonb
                )
            )");
            DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_help_open_consistency CHECK (
                help_open = false OR (
                    help_categories IS NOT NULL
                    AND help_sought IS NOT NULL
                    AND char_length(help_sought) BETWEEN 30 AND 1500
                )
            )');
            DB::statement("ALTER TABLE profiles ADD CONSTRAINT profiles_preferred_help_categories_shape CHECK (
                preferred_help_categories IS NULL OR (
                    jsonb_typeof(preferred_help_categories) = 'array'
                    AND jsonb_array_length(preferred_help_categories) BETWEEN 1 AND 5
                    AND preferred_help_categories <@ '[\"usability_feedback\", \"code_review\", \"reproduce_behavior\", \"explain_concept\", \"documentation\"]'::jsonb
                )
            )");
            DB::statement("ALTER TABLE help_offers ADD CONSTRAINT help_offers_category_value CHECK (category IN ('usability_feedback', 'code_review', 'reproduce_behavior', 'explain_concept', 'documentation'))");
            DB::statement("ALTER TABLE help_offers ADD CONSTRAINT help_offers_state_value CHECK (state IN ('pending', 'accepted', 'declined', 'withdrawn', 'expired'))");
            DB::statement('ALTER TABLE help_offers ADD CONSTRAINT help_offers_lock_version_positive CHECK (lock_version >= 1)');
            DB::statement('ALTER TABLE help_offers ADD CONSTRAINT help_offers_summary_size CHECK (char_length(public_summary) BETWEEN 30 AND 800)');
            DB::statement('ALTER TABLE help_offers ADD CONSTRAINT help_offers_outcome_size CHECK (char_length(expected_outcome) BETWEEN 20 AND 500)');
            DB::statement('CREATE UNIQUE INDEX help_offers_one_pending ON help_offers (project_id, proposer_id) WHERE state = \'pending\'');
            DB::statement('ALTER TABLE help_offers ADD CONSTRAINT help_offers_decision_consistency CHECK (
                (state = \'pending\' AND decided_at IS NULL AND accepted_at IS NULL AND accepted_by IS NULL AND accepted_request_id IS NULL)
                OR (state = \'accepted\' AND decided_at IS NOT NULL AND accepted_at IS NOT NULL AND accepted_by IS NOT NULL AND accepted_request_id IS NOT NULL)
                OR (state IN (\'declined\', \'withdrawn\', \'expired\') AND decided_at IS NOT NULL AND accepted_at IS NULL AND accepted_by IS NULL AND accepted_request_id IS NULL)
            )');
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::statement('LOCK TABLE help_offers, projects, profiles IN ACCESS EXCLUSIVE MODE');
            if (DB::table('help_offers')->exists()
                || DB::table('projects')->where('help_open', true)->exists()
                || DB::table('profiles')->whereNotNull('preferred_help_categories')->exists()) {
                throw new RuntimeException('Retour BH01 refusé : offres ou choix de catégories connus à conserver.');
            }
            Schema::drop('help_offers');
            DB::statement('ALTER TABLE projects DROP CONSTRAINT projects_help_categories_shape, DROP CONSTRAINT projects_help_open_consistency');
            DB::statement('ALTER TABLE profiles DROP CONSTRAINT profiles_preferred_help_categories_shape');
            Schema::table('projects', function (Blueprint $table): void {
                $table->dropIndex(['help_open', 'publication_state', 'published_at', 'id']);
                $table->dropColumn(['help_open', 'help_categories']);
            });
            Schema::table('profiles', function (Blueprint $table): void {
                $table->dropColumn('preferred_help_categories');
            });
        });
    }
};

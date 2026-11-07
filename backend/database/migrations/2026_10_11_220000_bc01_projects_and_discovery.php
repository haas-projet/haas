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
            Schema::create('projects', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
                // An incomplete private draft has unknown text, not invented publication content.
                $table->text('name')->nullable();
                $table->text('summary')->nullable();
                $table->text('description')->nullable();
                $table->string('project_stage', 32)->default('idea');
                $table->string('publication_state', 32)->default('draft');
                $table->integer('lock_version')->default(1);
                $table->text('help_sought')->nullable();
                $table->text('demo_url')->nullable();
                $table->text('repository_url')->nullable();
                $table->timestampTz('published_at')->nullable();
                $table->timestampTz('hidden_at')->nullable();
                $table->text('moderation_reason')->nullable();
                $table->timestampsTz();
                $table->unique(['id', 'owner_id']);
                $table->index(['publication_state', 'published_at', 'id']);
                $table->index(['owner_id', 'created_at']);
            });
            DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_stage_values CHECK (project_stage IN ('idea', 'prototype', 'in_development', 'launched'))");
            DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_publication_values CHECK (publication_state IN ('draft', 'published', 'archived'))");
            DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_lock_version_positive CHECK (lock_version >= 1)');
            foreach (['name' => [3, 80], 'summary' => [30, 240], 'description' => [80, 4000]] as $column => [$min, $max]) {
                DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_{$column}_size CHECK ({$column} IS NULL OR char_length({$column}) BETWEEN {$min} AND {$max})");
            }
            DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_help_sought_size CHECK (help_sought IS NULL OR char_length(help_sought) <= 1500)');
            DB::statement(<<<'SQL'
                ALTER TABLE projects ADD CONSTRAINT projects_publication_consistency CHECK (
                    (publication_state <> 'draft' OR published_at IS NULL)
                    AND (publication_state <> 'published' OR published_at IS NOT NULL)
                    AND (published_at IS NULL OR (name IS NOT NULL AND summary IS NOT NULL AND description IS NOT NULL))
                )
                SQL);
            // These links are inert data. BC02 additionally validates complete URLs; nothing fetches them.
            foreach (['demo_url', 'repository_url'] as $column) {
                DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_{$column}_https CHECK ({$column} IS NULL OR (char_length({$column}) <= 2048 AND {$column} ~* '^https://[^/@?#[:space:][:cntrl:]]+([/?#]|$)' AND {$column} !~ '[[:space:][:cntrl:]]'))");
            }
            Schema::create('project_technologies', function (Blueprint $table): void {
                $table->foreignUuid('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignUuid('technology_id')->constrained('technologies')->restrictOnDelete();
                $table->primary(['project_id', 'technology_id']);
                $table->timestampsTz();
                $table->index(['technology_id', 'project_id']);
            });
            Schema::table('profiles', function (Blueprint $table): void {
                $table->boolean('directory_visible')->default(false);
                $table->string('help_availability', 32)->default('not_specified');
                $table->text('contribution_interests')->nullable();
                $table->timestampTz('availability_updated_at')->nullable();
            });
            DB::statement("ALTER TABLE profiles ADD CONSTRAINT profiles_help_availability_values CHECK (help_availability IN ('not_specified', 'available', 'unavailable'))");
            DB::statement('ALTER TABLE profiles ADD CONSTRAINT profiles_contribution_interests_size CHECK (contribution_interests IS NULL OR char_length(contribution_interests) <= 300)');
            DB::statement("ALTER TABLE profiles ADD CONSTRAINT profiles_availability_declaration_date CHECK (help_availability = 'not_specified' OR availability_updated_at IS NOT NULL)");
            Schema::table('help_requests', function (Blueprint $table): void {
                // NULL preserves standalone legacy discussions. Ownership is enforced even for direct SQL.
                $table->uuid('project_id')->nullable();
                $table->foreign(['project_id', 'author_id'], 'help_requests_project_owner_foreign')
                    ->references(['id', 'owner_id'])->on('projects')->restrictOnDelete();
                $table->index(['project_id', 'created_at']);
            });
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            // Hold all affected tables until the final DDL: no choice/link may race the preflight.
            DB::statement('LOCK TABLE projects, project_technologies, profiles, help_requests IN ACCESS EXCLUSIVE MODE');
            $knownPreference = DB::table('profiles')->where('directory_visible', true)
                ->orWhere('help_availability', '<>', 'not_specified')
                ->orWhereNotNull('contribution_interests')->orWhereNotNull('availability_updated_at')->exists();
            if (DB::table('projects')->exists() || DB::table('project_technologies')->exists()
                || DB::table('help_requests')->whereNotNull('project_id')->exists() || $knownPreference) {
                throw new RuntimeException('Retour BC01 refusé : projets, rattachements et préférences connus à conserver.');
            }
            Schema::table('help_requests', function (Blueprint $table): void {
                $table->dropForeign('help_requests_project_owner_foreign');
                $table->dropColumn('project_id');
            });
            DB::statement('ALTER TABLE profiles DROP CONSTRAINT profiles_help_availability_values, DROP CONSTRAINT profiles_contribution_interests_size, DROP CONSTRAINT profiles_availability_declaration_date');
            Schema::table('profiles', function (Blueprint $table): void {
                $table->dropColumn(['directory_visible', 'help_availability', 'contribution_interests', 'availability_updated_at']);
            });
            Schema::drop('project_technologies');
            Schema::drop('projects');
        });
    }
};

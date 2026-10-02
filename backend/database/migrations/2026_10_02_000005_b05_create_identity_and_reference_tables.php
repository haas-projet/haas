<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL exécute cette migration dans une transaction ; figer le précontrôle et la reprise.
        DB::statement('LOCK TABLE users IN ACCESS EXCLUSIVE MODE');
        $invalid = DB::table('users')->whereRaw("char_length(btrim(name)) NOT BETWEEN 3 AND 30 OR name ~ '[[:cntrl:]]' OR btrim(email) = '' OR btrim(email) ~ '[[:space:]]'")->exists();
        $duplicateEmail = DB::table('users')->selectRaw('lower(btrim(email))')->groupByRaw('lower(btrim(email))')->havingRaw('count(*) > 1')->exists();
        $duplicateHandle = DB::table('users')->selectRaw('lower(btrim(name))')->groupByRaw('lower(btrim(name))')->havingRaw('count(*) > 1')->exists();
        if ($invalid || $duplicateEmail || $duplicateHandle) {
            throw new RuntimeException('Migration B05 refusée : corriger les identités existantes invalides ou en doublon avant de relancer.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('handle', 30)->nullable();
            $table->enum('role', ['member', 'moderator', 'admin'])->default('member');
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->boolean('is_demo')->default(false);
        });
        DB::table('users')->update(['handle' => DB::raw('btrim(name)'), 'email' => DB::raw('lower(btrim(email))')]);
        DB::statement('ALTER TABLE users ALTER COLUMN handle SET NOT NULL, ALTER COLUMN name DROP NOT NULL');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_handle_format CHECK (char_length(handle) BETWEEN 3 AND 30 AND handle = btrim(handle) AND handle !~ '[[:cntrl:]]')");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_email_normalized CHECK (email = lower(btrim(email)) AND email <> '' AND email !~ '[[:space:]]')");
        DB::statement('CREATE UNIQUE INDEX users_handle_unique ON users (lower(handle))');

        Schema::create('profiles', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('bio', 500)->default('');
            $table->string('country', 100)->nullable();
            $table->string('primary_language', 35)->default('fr');
            $table->string('github_url', 2048)->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE profiles ADD CONSTRAINT profiles_language_not_blank CHECK (btrim(primary_language) <> '')");

        Schema::create('technologies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 80);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE technologies ADD CONSTRAINT technologies_slug_format CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'), ADD CONSTRAINT technologies_name_not_blank CHECK (btrim(name) <> '')");

        Schema::create('user_technologies', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('technology_id')->constrained('technologies')->restrictOnDelete();
            $table->primary(['user_id', 'technology_id']);
            $table->index(['technology_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_technologies');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('technologies');
        // Les comptes créés depuis B05 récupèrent un nom compatible avec l'ancien schéma.
        DB::table('users')->whereNull('name')->update(['name' => DB::raw('handle')]);
        DB::statement('ALTER TABLE users ALTER COLUMN name SET NOT NULL, DROP CONSTRAINT users_handle_format, DROP CONSTRAINT users_email_normalized');
        DB::statement('DROP INDEX users_handle_unique');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['handle', 'role', 'status', 'is_demo']);
        });
    }
};

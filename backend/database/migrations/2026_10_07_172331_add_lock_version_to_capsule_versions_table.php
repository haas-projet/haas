<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Verrou optimiste lock_version aligné sur le patron de help_requests
// (backend/database/migrations/2026_10_03_180000_create_help_requests_table.php:37
// « $table->unsignedInteger('lock_version')->default(1) »). Migration
// additive : les versions existantes reçoivent 1 par défaut.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capsule_versions', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(1)->after('published_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('capsule_versions') || ! Schema::hasColumn('capsule_versions', 'lock_version')) {
            return;
        }
        Schema::table('capsule_versions', function (Blueprint $table): void {
            $table->dropColumn('lock_version');
        });
    }
};

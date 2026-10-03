<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->integer('lock_version')->default(1);
        });
        Schema::table('proposals', function (Blueprint $table): void {
            $table->integer('lock_version')->default(1);
        });

        // PostgreSQL ne traduit pas unsignedInteger en contrainte de positivité.
        foreach (['help_requests', 'comments', 'proposals'] as $tableName) {
            DB::statement("ALTER TABLE {$tableName} ADD CONSTRAINT {$tableName}_lock_version_positive CHECK (lock_version > 0)");
        }
    }

    public function down(): void
    {
        foreach (['help_requests', 'comments', 'proposals'] as $tableName) {
            DB::statement("ALTER TABLE {$tableName} DROP CONSTRAINT {$tableName}_lock_version_positive");
        }

        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn('lock_version');
        });
        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropColumn('lock_version');
        });
    }
};

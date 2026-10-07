<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_requests', function (Blueprint $table): void {
            $table->text('goal')->nullable()->change();
            $table->text('expected')->nullable()->change();
            $table->text('observed')->nullable()->change();
            $table->text('attempts')->nullable()->change();
            $table->string('environment', 255)->nullable()->change();
            $table->string('help_intent', 32)->default('unblock');
            $table->text('code')->nullable();
            $table->string('code_language', 40)->nullable();
            $table->string('primary_language', 35)->default('fr');
            $table->string('reproduction_url', 2048)->nullable();
            $table->timestampTz('hidden_at')->nullable();
        });
        DB::statement("ALTER TABLE help_requests ADD CONSTRAINT help_requests_intent_values CHECK (help_intent IN ('unblock','review_solution','reproduce_behavior','ask_question'))");
    }

    public function down(): void
    {
        if (DB::table('help_requests')->whereNull('goal')->orWhereNull('expected')->orWhereNull('observed')->orWhereNull('attempts')->orWhereNull('environment')->exists()) {
            throw new RuntimeException('Retour B14 refusé : des brouillons ou questions utilisent des champs facultatifs.');
        }
        DB::statement('ALTER TABLE help_requests DROP CONSTRAINT help_requests_intent_values');
        Schema::table('help_requests', function (Blueprint $table): void {
            $table->text('goal')->nullable(false)->change();
            $table->text('expected')->nullable(false)->change();
            $table->text('observed')->nullable(false)->change();
            $table->text('attempts')->nullable(false)->change();
            $table->string('environment', 255)->nullable(false)->change();
            $table->dropColumn(['help_intent', 'code', 'code_language', 'primary_language', 'reproduction_url', 'hidden_at']);
        });
    }
};

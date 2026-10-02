<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_terms_acceptances', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('version', 64);
            $table->timestampTz('accepted_at');
            $table->primary(['user_id', 'version']);
        });
        DB::statement("ALTER TABLE user_terms_acceptances ADD CONSTRAINT user_terms_version_format CHECK (version ~ '^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_terms_acceptances');
    }
};

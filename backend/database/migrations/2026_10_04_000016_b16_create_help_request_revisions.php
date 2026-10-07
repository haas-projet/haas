<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_request_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained('help_requests')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('request_version');
            $table->string('action', 16);
            $table->boolean('is_public');
            $table->jsonb('changed_fields');
            $table->text('edit_note')->nullable();
            $table->timestampTz('occurred_at');
            $table->unique(['request_id', 'request_version']);
        });
        DB::statement("ALTER TABLE help_request_revisions ADD CONSTRAINT help_request_revisions_valid CHECK (request_version > 0 AND action IN ('updated', 'published') AND jsonb_typeof(changed_fields) = 'array' AND (edit_note IS NULL OR char_length(edit_note) BETWEEN 20 AND 1000) AND (action <> 'published' OR is_public))");
    }

    public function down(): void
    {
        Schema::dropIfExists('help_request_revisions');
    }
};

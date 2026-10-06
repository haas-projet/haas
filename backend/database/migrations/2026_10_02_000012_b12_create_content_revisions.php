<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resource_type', 64);
            $table->uuid('resource_id');
            $table->bigInteger('revision');
            $table->string('action', 64);
            $table->jsonb('metadata');
            $table->timestampTz('occurred_at');
            $table->timestampTz('redacted_at')->nullable();
            $table->unique(['resource_type', 'resource_id', 'revision'], 'content_revisions_resource_version_unique');
            $table->index(['actor_id', 'occurred_at']);
        });
        DB::statement('ALTER TABLE content_revisions ADD CONSTRAINT content_revisions_positive CHECK (revision > 0)');
        DB::statement("ALTER TABLE content_revisions ADD CONSTRAINT content_revisions_metadata_object CHECK (jsonb_typeof(metadata) = 'object')");
        DB::statement("ALTER TABLE content_revisions ADD CONSTRAINT content_revisions_redacted_empty CHECK (redacted_at IS NULL OR metadata = '{}'::jsonb)");
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
    }
};

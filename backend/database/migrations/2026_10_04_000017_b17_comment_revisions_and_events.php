<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('comment_id')->constrained('comments')->cascadeOnDelete();
            $table->integer('comment_version');
            $table->string('action', 16);
            $table->text('body');
            $table->timestampTz('occurred_at');
            $table->unique(['comment_id', 'comment_version']);
        });
        DB::statement("ALTER TABLE comment_revisions ADD CONSTRAINT comment_revisions_valid CHECK (comment_version > 0 AND action IN ('created', 'updated', 'before_edit') AND char_length(body) BETWEEN 1 AND 4000)");
        foreach (['notification_outbox', 'internal_notifications'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_kind");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_kind CHECK (kind IN ('profile.moderated', 'comment.created'))");
        }
    }

    public function down(): void
    {
        // Refus explicite plutôt que supprimer des événements pour faire passer un rollback.
        foreach (['notification_outbox', 'internal_notifications'] as $table) {
            if (DB::table($table)->where('kind', 'comment.created')->exists()) {
                throw new RuntimeException('Retour B17 refusé : événements de commentaires à conserver.');
            }
        }
        foreach (['notification_outbox', 'internal_notifications'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_kind");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_kind CHECK (kind = 'profile.moderated')");
        }
        Schema::dropIfExists('comment_revisions');
    }
};

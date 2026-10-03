<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['notification_outbox', 'internal_notifications'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->uuid('id')->primary();
                $table->uuid('event_id');
                $table->foreignUuid('recipient_id')->constrained('users')->cascadeOnDelete();
                $table->string('kind', 64);
                $table->timestampTz('created_at');
                $table->timestampTz($name === 'notification_outbox' ? 'delivered_at' : 'read_at')->nullable();
                $table->unique(['recipient_id', 'event_id']);
                $table->index(['recipient_id', 'created_at', 'id']);
            });
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_kind CHECK (kind = 'profile.moderated')");
        }
        DB::statement('CREATE INDEX notification_outbox_pending ON notification_outbox (created_at, id) WHERE delivered_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_notifications');
        Schema::dropIfExists('notification_outbox');
    }
};

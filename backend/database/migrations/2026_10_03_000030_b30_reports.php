<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->timestampTz('hidden_at')->nullable();
        });
        Schema::create('reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resource_type', 32);
            $table->uuid('resource_id');
            $table->string('category', 32);
            $table->text('detail_encrypted');
            $table->string('status', 24)->default('new');
            $table->integer('lock_version')->default(0);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->index(['status', 'created_at', 'id']);
            $table->index(['reporter_id', 'created_at']);
        });
        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_types CHECK (resource_type = 'profile' AND status IN ('new','in_review','resolved','dismissed') AND category IN ('secret_exposed','abuse','uncertain_rights','misleading_solution','other') AND lock_version >= 0)");
        DB::statement("CREATE UNIQUE INDEX reports_one_active ON reports (reporter_id, resource_type, resource_id) WHERE status IN ('new','in_review')");
        Schema::create('report_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('reports');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 24);
            $table->text('reason_encrypted');
            $table->integer('report_version');
            $table->timestampTz('created_at');
            $table->unique(['report_id', 'report_version']);
        });
        DB::statement("ALTER TABLE report_decisions ADD CONSTRAINT report_decisions_action CHECK (action IN ('review','dismiss','request_correction','hide') AND report_version > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('report_decisions');
        Schema::dropIfExists('reports');
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('hidden_at'));
    }
};

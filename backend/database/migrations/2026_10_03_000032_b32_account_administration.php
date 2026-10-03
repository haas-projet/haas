<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->integer('security_version')->default(0);
        });
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_security_version_positive CHECK (security_version >= 0)');
        Schema::create('administration_guard', function (Blueprint $table): void {
            $table->integer('id')->primary();
        });
        DB::table('administration_guard')->insert(['id' => 1]);
        Schema::create('account_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('field', 16);
            $table->string('previous', 16);
            $table->string('current', 16);
            $table->integer('security_version');
            $table->text('reason_encrypted');
            $table->timestampTz('created_at');
            $table->unique(['user_id', 'security_version']);
        });
        DB::statement("ALTER TABLE account_decisions ADD CONSTRAINT account_decisions_field CHECK (field IN ('status', 'role') AND security_version > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('account_decisions');
        Schema::dropIfExists('administration_guard');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('security_version'));
    }
};

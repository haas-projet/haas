<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_idempotency', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('route_target', 255);
            $table->char('key_hash', 64);
            $table->char('payload_hash', 64);
            $table->smallInteger('status');
            $table->jsonb('response');
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at')->index();
            $table->unique(['user_id', 'route_target', 'key_hash'], 'api_idempotency_intention_unique');
        });
        DB::statement('ALTER TABLE api_idempotency ADD CONSTRAINT api_idempotency_expiry CHECK (expires_at > created_at)');
        DB::statement('ALTER TABLE api_idempotency ADD CONSTRAINT api_idempotency_status CHECK (status IN (200, 201, 202, 204))');
        DB::statement("ALTER TABLE api_idempotency ADD CONSTRAINT api_idempotency_response CHECK (jsonb_typeof(response) = 'object' AND octet_length(response::text) <= 2048)");
        DB::statement("ALTER TABLE api_idempotency ADD CONSTRAINT api_idempotency_hashes CHECK (key_hash ~ '^[0-9a-f]{64}$' AND payload_hash ~ '^[0-9a-f]{64}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency');
    }
};

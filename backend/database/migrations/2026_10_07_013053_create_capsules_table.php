<?php

use App\Enums\Capsules\CapsuleVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capsules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 120);
            // FK vers help_requests ajoutée dans une migration ultérieure (B11 absent de main au 2026-10-07).
            $table->uuid('source_request_id')->nullable();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('editorial_origin', 100)->nullable();
            $table->enum('visibility', array_map(fn (CapsuleVisibility $v) => $v->value, CapsuleVisibility::cases()))
                ->default(CapsuleVisibility::Visible->value);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX capsules_slug_unique ON capsules (lower(slug))');
        DB::statement("ALTER TABLE capsules ADD CONSTRAINT capsules_slug_format CHECK (char_length(slug) BETWEEN 3 AND 120 AND slug = lower(btrim(slug)) AND slug ~ '^[a-z0-9][a-z0-9-]{1,118}[a-z0-9]$')");
        // Source : demande résolue XOR origine éditoriale explicite ; jamais les deux ni aucune.
        DB::statement('ALTER TABLE capsules ADD CONSTRAINT capsules_source_xor CHECK ((source_request_id IS NULL) <> (editorial_origin IS NULL))');
        DB::statement("ALTER TABLE capsules ADD CONSTRAINT capsules_editorial_origin_format CHECK (editorial_origin IS NULL OR (char_length(btrim(editorial_origin)) BETWEEN 3 AND 100 AND editorial_origin !~ '[[:cntrl:]]'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('capsules');
    }
};

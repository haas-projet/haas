<?php

use App\Enums\Capsules\CapsuleVersionState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capsule_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('capsule_id')->constrained('capsules')->cascadeOnDelete();
            $table->string('version_label', 40);
            $table->text('body');
            $table->text('limits')->nullable();
            $table->enum('state', array_map(fn (CapsuleVersionState $s) => $s->value, CapsuleVersionState::cases()))
                ->default(CapsuleVersionState::Draft->value);
            $table->foreignUuid('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();

            $table->unique(['capsule_id', 'version_label'], 'capsule_versions_capsule_label_unique');
        });

        DB::statement("ALTER TABLE capsule_versions ADD CONSTRAINT capsule_versions_label_format CHECK (char_length(version_label) BETWEEN 1 AND 40 AND version_label = btrim(version_label) AND version_label !~ '[[:cntrl:]]')");
        DB::statement('ALTER TABLE capsule_versions ADD CONSTRAINT capsule_versions_body_nonempty CHECK (char_length(btrim(body)) > 0)');
        DB::statement("ALTER TABLE capsule_versions ADD CONSTRAINT capsule_versions_published_at_requires_state CHECK (published_at IS NULL OR state = 'published')");
    }

    public function down(): void
    {
        Schema::dropIfExists('capsule_versions');
    }
};

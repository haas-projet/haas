<?php

use App\Enums\Capsules\ArtifactDistributionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('version_id')->constrained('capsule_versions')->cascadeOnDelete();
            $table->string('private_path', 1024);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->enum('distribution_status', array_map(fn (ArtifactDistributionStatus $s) => $s->value, ArtifactDistributionStatus::cases()))
                ->default(ArtifactDistributionStatus::Inactive->value);
            $table->string('notices_path', 1024)->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE artifacts ADD CONSTRAINT artifacts_private_path_format CHECK (char_length(private_path) BETWEEN 1 AND 1024 AND private_path = btrim(private_path) AND private_path !~ '[[:cntrl:]]')");
        DB::statement("ALTER TABLE artifacts ADD CONSTRAINT artifacts_sha256_format CHECK (sha256 ~ '^[0-9a-f]{64}$')");
        DB::statement('ALTER TABLE artifacts ADD CONSTRAINT artifacts_size_positive CHECK (size > 0)');
        DB::statement("ALTER TABLE artifacts ADD CONSTRAINT artifacts_notices_path_format CHECK (notices_path IS NULL OR (char_length(notices_path) BETWEEN 1 AND 1024 AND notices_path = btrim(notices_path) AND notices_path !~ '[[:cntrl:]]'))");
        // Rend « rejouer deux approbations identiques » visible côté SQL : un seul artefact actif par version et digest.
        DB::statement("CREATE UNIQUE INDEX artifacts_version_sha_approved_unique ON artifacts (version_id, sha256) WHERE distribution_status = 'approved'");
    }

    public function down(): void
    {
        Schema::dropIfExists('artifacts');
    }
};

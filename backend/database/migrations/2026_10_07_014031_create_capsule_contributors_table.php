<?php

use App\Enums\Capsules\ContributionRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capsule_contributors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('version_id')->constrained('capsule_versions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('contribution_role', array_map(fn (ContributionRole $r) => $r->value, ContributionRole::cases()));
            $table->timestamps();

            $table->unique(['version_id', 'user_id', 'contribution_role'], 'capsule_contributors_triplet_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capsule_contributors');
    }
};

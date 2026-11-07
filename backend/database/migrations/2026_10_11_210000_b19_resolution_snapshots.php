<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resolutions', function (Blueprint $table): void {
            // Legacy rows retain unknown context/version; there is no backfill.
            $table->text('environment')->nullable();
            $table->integer('proposal_version')->nullable();
        });
        DB::statement('ALTER TABLE resolutions ADD CONSTRAINT resolutions_environment_size CHECK (environment IS NULL OR char_length(environment) BETWEEN 1 AND 255)');
        DB::statement('ALTER TABLE resolutions ADD CONSTRAINT resolutions_proposal_version_positive CHECK (proposal_version IS NULL OR proposal_version >= 1)');
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::statement('LOCK TABLE resolutions IN ACCESS EXCLUSIVE MODE');
            if (DB::table('resolutions')->whereNotNull('environment')->orWhereNotNull('proposal_version')->exists()) {
                throw new RuntimeException('Retour B19 refusé : contexte et version de résolution à conserver.');
            }
            Schema::table('resolutions', function (Blueprint $table): void {
                $table->dropColumn(['environment', 'proposal_version']);
            });
        });
    }
};

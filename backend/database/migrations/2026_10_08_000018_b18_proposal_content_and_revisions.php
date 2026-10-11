<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table): void {
            $table->text('code')->nullable();
            $table->timestampTz('edited_at')->nullable();
            $table->timestampTz('hidden_at')->nullable();
        });
        DB::statement('ALTER TABLE proposals ADD CONSTRAINT proposals_code_length CHECK (code IS NULL OR char_length(code) <= 12000)');
        Schema::create('proposal_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('proposal_id')->constrained('proposals')->restrictOnDelete();
            $table->integer('proposal_version');
            $table->string('action', 16);
            foreach (['diagnosis', 'fix', 'verification', 'limits'] as $field) {
                $table->text($field);
            }
            $table->text('code')->nullable();
            $table->timestampTz('occurred_at');
            $table->unique(['proposal_id', 'proposal_version']);
        });
        // before_edit captures legacy text faithfully, including lengths not validated by B11.
        DB::statement("ALTER TABLE proposal_revisions ADD CONSTRAINT proposal_revisions_valid CHECK (proposal_version > 0 AND action IN ('created', 'updated', 'before_edit') AND (action = 'before_edit' OR (char_length(diagnosis) BETWEEN 20 AND 4000 AND char_length(fix) BETWEEN 20 AND 4000 AND char_length(verification) BETWEEN 20 AND 4000 AND char_length(limits) BETWEEN 20 AND 4000 AND (code IS NULL OR char_length(code) <= 12000))))");
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::statement('LOCK TABLE proposals IN ACCESS EXCLUSIVE MODE');
            DB::statement('LOCK TABLE proposal_revisions IN ACCESS EXCLUSIVE MODE');
            if (DB::table('proposal_revisions')->exists() || DB::table('proposals')->whereNotNull('code')->orWhereNotNull('edited_at')->orWhereNotNull('hidden_at')->exists()) {
                throw new RuntimeException('Retour B18 refusé : contenu et historique à conserver.');
            }
            Schema::dropIfExists('proposal_revisions');
            DB::statement('ALTER TABLE proposals DROP CONSTRAINT proposals_code_length');
            Schema::table('proposals', function (Blueprint $table): void {
                $table->dropColumn(['code', 'edited_at', 'hidden_at']);
            });
        });
    }
};

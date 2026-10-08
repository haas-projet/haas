<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Les anciennes notes ne reçoivent pas une révision inventée.
        Schema::table('capsule_version_reviews', function (Blueprint $table): void {
            $table->unsignedInteger('reviewed_lock_version')->nullable();
            $table->dropForeign(['version_id']);
            $table->foreign('version_id')->references('id')->on('capsule_versions')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE capsule_version_reviews ADD CONSTRAINT capsule_reviews_positive_version CHECK (reviewed_lock_version IS NULL OR reviewed_lock_version > 0)');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION preserve_capsule_review_history() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'capsule_review_history_immutable' USING ERRCODE = '23514';
END;
$$;
CREATE TRIGGER capsule_review_history_immutable BEFORE UPDATE OR DELETE ON capsule_version_reviews
FOR EACH ROW EXECUTE FUNCTION preserve_capsule_review_history();
SQL);
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            if (Schema::hasTable('capsule_version_reviews')) {
                // Le verrou empêche une insertion entre le contrôle et le DDL.
                DB::statement('LOCK TABLE capsule_version_reviews IN ACCESS EXCLUSIVE MODE');
                if (DB::table('capsule_version_reviews')->whereNotNull('reviewed_lock_version')->exists()) {
                    throw new RuntimeException('Rollback B24 refusé : les versions réellement relues doivent conserver leur attribution.');
                }
                DB::statement('DROP TRIGGER IF EXISTS capsule_review_history_immutable ON capsule_version_reviews');
                DB::statement('ALTER TABLE capsule_version_reviews DROP CONSTRAINT IF EXISTS capsule_reviews_positive_version');
                Schema::table('capsule_version_reviews', function (Blueprint $table): void {
                    $table->dropForeign(['version_id']);
                    $table->foreign('version_id')->references('id')->on('capsule_versions')->cascadeOnDelete();
                    $table->dropColumn('reviewed_lock_version');
                });
            }
            DB::statement('DROP FUNCTION IF EXISTS preserve_capsule_review_history()');
        });
    }
};

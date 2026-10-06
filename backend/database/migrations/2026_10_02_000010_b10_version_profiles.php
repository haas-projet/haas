<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('LOCK TABLE user_technologies IN SHARE ROW EXCLUSIVE MODE');
        if (DB::table('user_technologies')->select('user_id')->groupBy('user_id')->havingRaw('count(*) > 8')->exists()) {
            throw new RuntimeException('Migration B10 refusée : vérifier les profils dépassant huit technologies, sans suppression automatique.');
        }
        Schema::table('profiles', function (Blueprint $table): void {
            $table->integer('lock_version')->default(0);
        });
        DB::statement('ALTER TABLE profiles ADD CONSTRAINT profiles_version_nonnegative CHECK (lock_version >= 0)');
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn('lock_version');
        });
    }
};

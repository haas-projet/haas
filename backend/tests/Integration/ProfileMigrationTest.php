<?php

namespace Tests\Integration;

use App\Models\Profile;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
use Tests\PostgresTestCase;

final class ProfileMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_version_migration_preserves_existing_profiles_and_rolls_back_only_its_column(): void
    {
        $migration = require database_path('migrations/2026_10_02_000010_b10_version_profiles.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $user = User::factory()->create();
        Profile::factory()->for($user)->create(['bio' => 'À préserver']);
        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $this->assertFalse(Schema::hasColumn('profiles', 'lock_version'));
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'bio' => 'À préserver']);
        (new ReflectionMethod($migration, 'up'))->invoke($migration);
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'bio' => 'À préserver', 'lock_version' => 0]);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('profiles')->where('user_id', $user->id)->update(['lock_version' => -1]));
    }

    public function test_migration_refuses_existing_excess_technologies_without_dropping_any_selection(): void
    {
        $migration = require database_path('migrations/2026_10_02_000010_b10_version_profiles.php');
        $this->assertInstanceOf(Migration::class, $migration);
        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $user = User::factory()->create();
        $user->technologies()->attach(Technology::factory()->count(9)->create()->modelKeys());
        try {
            (new ReflectionMethod($migration, 'up'))->invoke($migration);
            $this->fail('Les anciens dépassements doivent être examinés explicitement.');
        } catch (RuntimeException $exception) {
            $this->assertStringStartsWith('Migration B10 refusée', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('profiles', 'lock_version'));
        $this->assertSame(9, $user->technologies()->count());
    }
}

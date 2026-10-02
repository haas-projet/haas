<?php

namespace Tests\Integration;

use App\Data\Identity\UpdateProfileData;
use App\Models\Profile;
use App\Models\User;
use App\Services\Identity\UpdateProfileService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\PostgresTestCase;

final class AuditMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_migration_round_trip_preserves_business_tables(): void
    {
        $owner = User::factory()->verified()->create();
        Profile::factory()->for($owner)->create(['bio' => 'À préserver']);
        $migration = require database_path('migrations/2026_10_02_000012_b12_create_content_revisions.php');
        $this->assertInstanceOf(Migration::class, $migration);
        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $this->assertFalse(Schema::hasTable('content_revisions'));
        $this->assertDatabaseHas('profiles', ['user_id' => $owner->id, 'bio' => 'À préserver']);
        (new ReflectionMethod($migration, 'up'))->invoke($migration);
        $this->assertTrue(Schema::hasTable('content_revisions'));
        $this->assertDatabaseCount('content_revisions', 0);
    }

    public function test_database_enforces_revision_identity_positive_number_object_and_redaction(): void
    {
        $owner = User::factory()->verified()->create();
        app(UpdateProfileService::class)->update($owner, new UpdateProfileData(0, ['bio' => 'Test'], null));
        $row = (array) DB::table('content_revisions')->sole();
        foreach ([['revision' => 0], ['metadata' => '[]', 'revision' => 2], ['revision' => 1],
            ['revision' => 2, 'redacted_at' => now(), 'metadata' => '{"legacy":"fixture"}'],
            ['revision' => 2, 'actor_id' => (string) Str::uuid()]] as $invalid) {
            try {
                DB::transaction(fn () => DB::table('content_revisions')->insert(array_replace($row, ['id' => (string) Str::uuid()], $invalid)));
                $this->fail('Contrainte SQL attendue.');
            } catch (QueryException) {
                $this->assertDatabaseCount('content_revisions', 1);
            }
        }
    }

    public function test_deleting_actor_keeps_history_without_a_dangling_account_reference(): void
    {
        $owner = User::factory()->verified()->create();
        app(UpdateProfileService::class)->update($owner, new UpdateProfileData(0, ['bio' => 'Test'], null));
        $id = $owner->id;
        $owner->delete();
        $row = DB::table('content_revisions')->sole();
        $this->assertNull($row->actor_id);
        $this->assertSame($id, $row->resource_id);
    }
}

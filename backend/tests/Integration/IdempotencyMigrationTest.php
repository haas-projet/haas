<?php

namespace Tests\Integration;

use App\Data\Idempotency\IdempotencyKey;
use App\Data\Identity\UpdateProfileData;
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

final class IdempotencyMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_migration_round_trip_preserves_profiles_and_revisions(): void
    {
        $owner = User::factory()->verified()->create();
        app(UpdateProfileService::class)->update($owner, new UpdateProfileData(0, ['bio' => 'À préserver'], null));
        $migration = require database_path('migrations/2026_10_03_000013_b13_create_api_idempotency.php');
        $this->assertInstanceOf(Migration::class, $migration);
        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $this->assertFalse(Schema::hasTable('api_idempotency'));
        $this->assertDatabaseCount('content_revisions', 1);
        $this->assertDatabaseHas('profiles', ['user_id' => $owner->id, 'bio' => 'À préserver']);
        (new ReflectionMethod($migration, 'up'))->invoke($migration);
        $this->assertTrue(Schema::hasTable('api_idempotency'));
    }

    public function test_database_enforces_unique_intention_and_bounded_success_result(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $owner = User::factory()->verified()->create();
        app(UpdateProfileService::class)->update($owner, new UpdateProfileData(0, ['bio' => 'Test'], null), new IdempotencyKey((string) Str::uuid()));
        $row = (array) DB::table('api_idempotency')->sole();
        foreach ([[], ['key_hash' => 'bad'], ['payload_hash' => 'bad'], ['expires_at' => $row['created_at']], ['status' => 500], ['response' => '[]'],
            ['response' => json_encode(['body' => str_repeat('a', 3000)], JSON_THROW_ON_ERROR)], ['user_id' => (string) Str::uuid()]] as $invalid) {
            try {
                $base = ['id' => (string) Str::uuid(), 'key_hash' => $invalid === [] ? $row['key_hash'] : hash('sha256', (string) Str::uuid())];
                DB::transaction(fn () => DB::table('api_idempotency')->insert(array_replace($row, $base, $invalid)));
                $this->fail('Contrainte SQL attendue.');
            } catch (QueryException) {
                $this->assertDatabaseCount('api_idempotency', 1);
            }
        }
        $owner->delete();
        $this->assertDatabaseCount('api_idempotency', 0);
        $this->assertDatabaseCount('content_revisions', 1);
    }
}

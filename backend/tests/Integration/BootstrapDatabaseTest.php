<?php

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class BootstrapDatabaseTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_migrations_store_uuid_users_and_session_references_on_postgresql(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertDatabaseCount('users', 0);
        $user = User::factory()->create();

        $this->assertTrue(Str::isUuid($user->getKey()));
        $this->assertSame('uuid', DB::selectOne(
            "select data_type from information_schema.columns where table_schema = 'public' and table_name = 'users' and column_name = 'id'",
        )->data_type);

        DB::table('sessions')->insert([
            'id' => 'bootstrap-test-session', 'user_id' => $user->getKey(),
            'payload' => '', 'last_activity' => time(),
        ]);
        $user->delete();
        $this->assertDatabaseHas('sessions', ['id' => 'bootstrap-test-session', 'user_id' => null]);
    }
}

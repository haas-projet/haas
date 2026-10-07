<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVisibility;
use App\Models\Capsules\Capsule;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class CapsulesSchemaTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_editorial_origin_capsule_is_persisted_with_default_visibility(): void
    {
        $owner = User::factory()->create();
        $capsule = Capsule::factory()->create(['owner_id' => $owner->id, 'slug' => 'ma-capsule']);

        $this->assertDatabaseHas('capsules', [
            'id' => $capsule->id,
            'slug' => 'ma-capsule',
            'owner_id' => $owner->id,
            'editorial_origin' => 'Démonstration éditoriale',
            'source_request_id' => null,
        ]);
        $this->assertSame(CapsuleVisibility::Visible, $capsule->refresh()->visibility);
    }

    public function test_capsule_rejects_both_source_request_and_editorial_origin(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsules_source_xor/i');

        Capsule::factory()->create([
            'source_request_id' => (string) Str::uuid(),
            'editorial_origin' => 'Démonstration',
        ]);
    }

    public function test_capsule_rejects_absence_of_source_and_editorial_origin(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsules_source_xor/i');

        Capsule::factory()->create([
            'source_request_id' => null,
            'editorial_origin' => null,
        ]);
    }

    public function test_slug_is_lowercased_and_unique_case_insensitively(): void
    {
        Capsule::factory()->create(['slug' => 'commune']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsules_slug_unique/i');

        Capsule::factory()->create(['slug' => 'COMMUNE']);
    }

    public function test_slug_format_is_enforced_by_check_constraint(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/capsules_slug_format/i');

        DB::table('capsules')->insert([
            'id' => (string) Str::uuid(),
            'slug' => 'A B',
            'owner_id' => User::factory()->create()->id,
            'editorial_origin' => 'editorial',
            'visibility' => 'visible',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_owner_deletion_is_restricted_while_capsule_exists(): void
    {
        $owner = User::factory()->create();
        Capsule::factory()->create(['owner_id' => $owner->id]);

        $this->expectException(QueryException::class);

        $owner->delete();
    }

    public function test_source_request_id_column_accepts_arbitrary_uuid_until_help_requests_fk_is_added(): void
    {
        $capsule = Capsule::factory()->create([
            'source_request_id' => $uuid = (string) Str::uuid(),
            'editorial_origin' => null,
        ]);

        $this->assertSame($uuid, $capsule->refresh()->source_request_id);
    }
}

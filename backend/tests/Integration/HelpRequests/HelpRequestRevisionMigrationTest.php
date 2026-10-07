<?php

namespace Tests\Integration\HelpRequests;

use App\Models\HelpRequest;
use App\Models\HelpRequestRevision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\PostgresTestCase;

final class HelpRequestRevisionMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_migration_roundtrip_preserves_request_and_duplicate_version_is_rejected(): void
    {
        $request = HelpRequest::factory()->create();
        $migration = require database_path('migrations/2026_10_04_000016_b16_create_help_request_revisions.php');
        $this->assertInstanceOf(Migration::class, $migration);
        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $this->assertFalse(Schema::hasTable('help_request_revisions'));
        (new ReflectionMethod($migration, 'up'))->invoke($migration);
        $this->assertDatabaseHas('help_requests', ['id' => $request->id, 'lock_version' => 1]);
        $row = $this->row($request);
        DB::table('help_request_revisions')->insert($row);
        try {
            DB::transaction(fn () => DB::table('help_request_revisions')->insert(array_replace($row, ['id' => (string) Str::uuid()])));
            $this->fail('Unicité attendue.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->getCode());
        }
        $request->delete();
        $this->assertDatabaseCount('help_request_revisions', 0);
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidRows')]
    public function test_sql_enforces_revision_invariants(array $changes): void
    {
        $request = HelpRequest::factory()->create();
        try {
            DB::transaction(fn () => DB::table('help_request_revisions')->insert(array_replace($this->row($request), $changes)));
            $this->fail('Contrainte attendue.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
        $this->assertDatabaseCount('help_request_revisions', 0);
        $this->assertSame([], (new HelpRequestRevision)->getFillable());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidRows(): iterable
    {
        yield 'version' => [['request_version' => 0]];
        yield 'action' => [['action' => 'deleted']];
        yield 'fields' => [['changed_fields' => '{}']];
        yield 'note' => [['edit_note' => 'court']];
        yield 'private publication' => [['action' => 'published', 'is_public' => false]];
    }

    /** @return array<string, mixed> */
    private function row(HelpRequest $request): array
    {
        return ['id' => (string) Str::uuid(), 'request_id' => $request->id, 'actor_id' => $request->author_id,
            'request_version' => 2, 'action' => 'updated', 'is_public' => false, 'changed_fields' => '["title"]', 'edit_note' => null, 'occurred_at' => now()->utc()];
    }
}

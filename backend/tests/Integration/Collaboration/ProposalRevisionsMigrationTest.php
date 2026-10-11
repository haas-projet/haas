<?php

namespace Tests\Integration\Collaboration;

use App\Models\Proposal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Tests\PostgresTestCase;

final class ProposalRevisionsMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_upgrade_preserves_legacy_content_and_versions_without_inventing_history(): void
    {
        $migration = $this->migration();
        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $this->assertFalse(Schema::hasTable('proposal_revisions'));
        $this->assertFalse(Schema::hasColumn('proposals', 'code'));
        $proposal = Proposal::factory()->create([
            'diagnosis' => 'Ancien', 'fix' => 'Texte', 'verification' => 'Court', 'limits' => 'Conservé', 'lock_version' => 7,
        ]);
        $original = $proposal->refresh()->getAttributes();

        foreach ([1, 2] as $iteration) {
            (new ReflectionMethod($migration, 'up'))->invoke($migration);
            $this->assertTrue(Schema::hasTable('proposal_revisions'));
            $this->assertDatabaseCount('proposal_revisions', 0);
            $proposal->refresh();
            foreach ($original as $field => $value) {
                $this->assertSame($value, $proposal->getRawOriginal($field), $field.' / '.$iteration);
            }
            foreach (['code', 'edited_at', 'hidden_at'] as $field) {
                $this->assertNull($proposal->getRawOriginal($field));
            }
            if ($iteration === 1) {
                (new ReflectionMethod($migration, 'down'))->invoke($migration);
            }
        }
    }

    #[DataProvider('protectedValues')]
    public function test_rollback_refuses_to_discard_new_content_or_dates(string $field, string $value): void
    {
        $proposal = Proposal::factory()->create([$field => $value]);
        $original = $proposal->refresh()->getAttributes();
        $this->assertRollbackRefused();
        $this->assertSame($original, $proposal->refresh()->getAttributes());
        $this->assertTrue(Schema::hasColumn('proposals', $field));
    }

    /** @return iterable<string, array{string, string}> */
    public static function protectedValues(): iterable
    {
        yield 'even empty code is known content' => ['code', ''];
        yield 'inert code' => ['code', "<script>fictif</script>\n"];
        yield 'edit date' => ['edited_at', '2026-10-08 12:00:00+00'];
        yield 'visibility date' => ['hidden_at', '2026-10-08 12:00:00+00'];
    }

    public function test_legacy_snapshot_is_faithful_and_prevents_destructive_rollback(): void
    {
        $proposal = Proposal::factory()->create(['diagnosis' => 'Court', 'lock_version' => 7]);
        $snapshot = $this->revision($proposal, ['action' => 'before_edit', 'proposal_version' => 7]);
        DB::table('proposal_revisions')->insert($snapshot);
        $this->assertDatabaseHas('proposal_revisions', $snapshot);
        $this->assertDatabaseCount('proposal_revisions', 1);
        $this->assertRollbackRefused();
        $this->assertDatabaseHas('proposal_revisions', $snapshot);
        $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'diagnosis' => 'Court', 'lock_version' => 7]);
    }

    /** @param array<string, int|string|null> $changes */
    #[DataProvider('invalidRevisions')]
    public function test_postgresql_rejects_invalid_new_revisions(array $changes): void
    {
        $proposal = Proposal::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('proposal_revisions')->insert($this->revision($proposal, $changes));
    }

    /** @return iterable<string, array{array<string, int|string|null>}> */
    public static function invalidRevisions(): iterable
    {
        yield 'zero version' => [['proposal_version' => 0]];
        yield 'negative legacy version' => [['action' => 'before_edit', 'proposal_version' => -1]];
        yield 'unknown action' => [['action' => 'accepted']];
        foreach (['diagnosis', 'fix', 'verification', 'limits'] as $field) {
            yield $field.' short' => [[$field => str_repeat('é', 19)]];
            yield $field.' long' => [[$field => str_repeat('é', 4001), 'action' => 'updated']];
        }
        yield 'long code' => [['code' => str_repeat('é', 12001)]];
    }

    public function test_postgresql_accepts_unicode_boundaries_without_changing_code(): void
    {
        $proposal = Proposal::factory()->create(['code' => str_repeat('é', 12000)]);
        foreach ([20, 4000] as $index => $length) {
            $changes = array_fill_keys(['diagnosis', 'fix', 'verification', 'limits'], str_repeat('é', $length));
            $snapshot = $this->revision($proposal, $changes + ['proposal_version' => $index + 1, 'code' => $proposal->code]);
            DB::table('proposal_revisions')->insert($snapshot);
            $this->assertDatabaseHas('proposal_revisions', $snapshot);
        }
        $this->assertSame(str_repeat('é', 12000), $proposal->refresh()->code);
    }

    public function test_postgresql_rejects_duplicate_proposal_versions(): void
    {
        $proposal = Proposal::factory()->create();
        $snapshot = $this->revision($proposal);
        DB::table('proposal_revisions')->insert($snapshot);
        $snapshot['id'] = (string) Str::uuid();
        $this->expectException(QueryException::class);
        DB::table('proposal_revisions')->insert($snapshot);
    }

    public function test_history_prevents_physical_proposal_deletion(): void
    {
        $proposal = Proposal::factory()->create();
        DB::table('proposal_revisions')->insert($this->revision($proposal));
        $this->expectException(QueryException::class);
        $proposal->delete();
    }

    public function test_postgresql_rejects_oversized_current_code(): void
    {
        $this->expectException(QueryException::class);
        Proposal::factory()->create(['code' => str_repeat('é', 12001)]);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_08_000018_b18_proposal_content_and_revisions.php');
    }

    private function assertRollbackRefused(): void
    {
        try {
            $migration = $this->migration();
            (new ReflectionMethod($migration, 'down'))->invoke($migration);
            $this->fail('Les données B18 doivent être conservées.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Retour B18 refusé : contenu et historique à conserver.', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('proposal_revisions'));
        $this->assertTrue(Schema::hasColumn('proposals', 'code'));
    }

    /** @param array<string, int|string|null> $changes
     * @return array<string, int|string|null>
     */
    private function revision(Proposal $proposal, array $changes = []): array
    {
        return array_replace([
            'id' => (string) Str::uuid(), 'proposal_id' => $proposal->id,
            'proposal_version' => 1, 'action' => 'created',
            'diagnosis' => $proposal->diagnosis, 'fix' => $proposal->fix,
            'verification' => $proposal->verification, 'limits' => $proposal->limits,
            'code' => $proposal->code, 'occurred_at' => '2026-10-08 12:00:00+00',
        ], $changes);
    }
}

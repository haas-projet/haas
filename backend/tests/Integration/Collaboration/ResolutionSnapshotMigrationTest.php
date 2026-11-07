<?php

namespace Tests\Integration\Collaboration;

use App\Models\HelpRequest;
use App\Models\Proposal;
use App\Models\User;
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

final class ResolutionSnapshotMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_upgrade_preserves_legacy_resolutions_without_inventing_environment_or_version(): void
    {
        $migration = $this->migration();
        (new ReflectionMethod($migration, 'down'))->invoke($migration);
        $this->assertFalse(Schema::hasColumn('resolutions', 'environment'));
        $this->assertFalse(Schema::hasColumn('resolutions', 'proposal_version'));
        $id = $this->legacyResolution();
        $original = (array) DB::table('resolutions')->where('id', $id)->sole();
        foreach ([1, 2] as $iteration) {
            (new ReflectionMethod($migration, 'up'))->invoke($migration);
            $this->assertTrue(Schema::hasColumn('resolutions', 'environment'));
            $this->assertTrue(Schema::hasColumn('resolutions', 'proposal_version'));
            $current = (array) DB::table('resolutions')->where('id', $id)->sole();
            foreach ($original as $field => $value) {
                $this->assertSame($value, $current[$field], $field.' / '.$iteration);
            }
            $this->assertNull($current['environment']);
            $this->assertNull($current['proposal_version']);
            $this->assertDatabaseCount('content_revisions', 0);
            $this->assertDatabaseCount('proposal_revisions', 0);
            $this->assertDatabaseCount('notification_outbox', 0);
            $this->assertDatabaseCount('internal_notifications', 0);
            if ($iteration === 1) {
                (new ReflectionMethod($migration, 'down'))->invoke($migration);
            }
        }
    }

    #[DataProvider('protectedSnapshots')]
    public function test_rollback_refuses_known_snapshots_even_for_revoked_resolutions(string $field, string|int $value, bool $revoked): void
    {
        $id = $this->legacyResolution();
        DB::table('resolutions')->where('id', $id)->update([$field => $value, 'revoked_at' => $revoked ? '2026-10-11 13:00:00+00' : null]);
        $original = (array) DB::table('resolutions')->where('id', $id)->sole();
        $level = DB::transactionLevel();
        try {
            $migration = $this->migration();
            (new ReflectionMethod($migration, 'down'))->invoke($migration);
            $this->fail('Les données connues de la résolution doivent être conservées.');
        } catch (RuntimeException $exception) {
            $this->assertStringStartsWith('Retour B19 refusé', $exception->getMessage());
        }
        $this->assertSame($level, DB::transactionLevel());
        $this->assertTrue(Schema::hasColumn('resolutions', 'environment'));
        $this->assertTrue(Schema::hasColumn('resolutions', 'proposal_version'));
        $this->assertSame($original, (array) DB::table('resolutions')->where('id', $id)->sole());
    }

    /** @return iterable<string, array{string, string|int, bool}> */
    public static function protectedSnapshots(): iterable
    {
        foreach ([false, true] as $revoked) {
            $state = $revoked ? 'revoked' : 'active';
            yield $state.' environment' => ['environment', 'é', $revoked];
            yield $state.' first version' => ['proposal_version', 1, $revoked];
            yield $state.' final integer version' => ['proposal_version', 2147483647, $revoked];
        }
    }

    #[DataProvider('validSnapshots')]
    public function test_postgresql_accepts_nullable_legacy_and_unicode_boundaries(?string $environment, ?int $version): void
    {
        $id = $this->legacyResolution();
        DB::table('resolutions')->where('id', $id)->update(['environment' => $environment, 'proposal_version' => $version]);
        $row = DB::table('resolutions')->where('id', $id)->sole();
        $this->assertSame($environment, $row->environment);
        $this->assertSame($version, $row->proposal_version);
    }

    /** @return iterable<string, array{?string, ?int}> */
    public static function validSnapshots(): iterable
    {
        yield 'unknown legacy snapshot' => [null, null];
        yield 'lower boundaries' => ['é', 1];
        yield 'upper Unicode boundaries' => [str_repeat('é', 255), 2147483647];
        yield 'known environment only' => ['Environnement historique connu.', null];
        yield 'known version only' => [null, 7];
    }

    /** @param array<string, string|int> $changes */
    #[DataProvider('invalidSnapshots')]
    public function test_postgresql_rejects_invalid_snapshot_lengths_and_versions(array $changes): void
    {
        $id = $this->legacyResolution();
        $this->expectException(QueryException::class);
        DB::table('resolutions')->where('id', $id)->update($changes);
    }

    /** @return iterable<string, array{array<string, string|int>}> */
    public static function invalidSnapshots(): iterable
    {
        yield 'empty environment' => [['environment' => '']];
        yield 'oversized Unicode environment' => [['environment' => str_repeat('é', 256)]];
        yield 'zero version' => [['proposal_version' => 0]];
        yield 'negative version' => [['proposal_version' => -1]];
        yield 'integer overflow' => [['proposal_version' => 2147483648]];
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_11_210000_b19_resolution_snapshots.php');
    }

    private function legacyResolution(): string
    {
        $owner = User::factory()->verified()->create();
        $contributor = User::factory()->verified()->create();
        $parent = HelpRequest::factory()->for($owner, 'author')->create(['state' => 'resolved', 'lock_version' => 8]);
        $proposal = Proposal::factory()->create(['request_id' => $parent->id, 'author_id' => $contributor->id, 'state' => 'accepted', 'lock_version' => 7]);
        $id = (string) Str::uuid();
        // B11 knew these fields, not the accepted proposal version or environment.
        DB::table('resolutions')->insert([
            'id' => $id, 'request_id' => $parent->id, 'proposal_id' => $proposal->id, 'accepted_by' => $owner->id,
            'validation_note' => 'Observation ancienne conservée sans reconstruction.',
            'accepted_at' => '2026-10-11 12:00:00+00', 'revoked_at' => null,
            'created_at' => '2026-10-11 12:00:00+00', 'updated_at' => '2026-10-11 12:00:00+00',
        ]);

        return $id;
    }
}

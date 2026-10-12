<?php

namespace Tests\Integration\Collaboration;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\PostgresTestCase;

final class RequestReopeningNotificationMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_down_up_twice_preserves_every_installed_kind_including_a_future_composed_kind(): void
    {
        $migration = require database_path('migrations/2026_10_12_000021_b20_reopening_notification_events.php');
        $recipient = User::factory()->verified()->create();
        foreach (['notification_outbox', 'internal_notifications'] as $table) {
            $check = DB::scalar('SELECT pg_get_expr(conbin, conrelid) FROM pg_constraint WHERE conrelid = CAST(? AS regclass) AND conname = ?', [$table, $table.'_kind']);
            $this->assertIsString($check);
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_kind");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_kind CHECK (({$check}) OR kind = 'capsule.version.published')");
            foreach (['profile.moderated', 'comment.created', 'proposal.created', 'resolution.accepted', 'capsule.version.published'] as $kind) {
                DB::table($table)->insert($this->event($recipient, $kind));
            }
        }
        foreach ([1, 2] as $iteration) {
            $migration->down();
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                $this->assertDatabaseCount($table, 5);
                $this->assertInsertRefused($table, $this->event($recipient, 'request.reopened'));
            }
            $migration->up();
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                $event = $this->event($recipient, 'request.reopened');
                DB::table($table)->insert($event);
                $this->assertDatabaseHas($table, $event);
                DB::table($table)->where('id', $event['id'])->delete();
                $this->assertDatabaseCount($table, 5);
            }
        }
    }

    #[DataProvider('tables')]
    public function test_down_refuses_known_reopening_events_atomically_in_both_tables(string $table): void
    {
        $recipient = User::factory()->verified()->create();
        $event = $this->event($recipient, 'request.reopened');
        DB::table($table)->insert($event);
        $migration = require database_path('migrations/2026_10_12_000021_b20_reopening_notification_events.php');
        $refused = false;
        try {
            $migration->down();
        } catch (RuntimeException $error) {
            $this->assertSame('Retour B20 refusé : événements de réouverture à conserver.', $error->getMessage());
            $refused = true;
        }
        $this->assertTrue($refused);
        $this->assertDatabaseHas($table, $event);
        foreach (['notification_outbox', 'internal_notifications'] as $other) {
            $next = $this->event($recipient, 'request.reopened');
            DB::table($other)->insert($next);
            $this->assertDatabaseHas($other, $next);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function tables(): iterable
    {
        yield 'outbox' => ['notification_outbox'];
        yield 'internal' => ['internal_notifications'];
    }

    /** @param array<string, string> $event */
    private function assertInsertRefused(string $table, array $event): void
    {
        $state = null;
        DB::beginTransaction();
        try {
            DB::table($table)->insert($event);
        } catch (QueryException $error) {
            $state = $error->errorInfo[0] ?? null;
        } finally {
            DB::rollBack();
        }
        $this->assertSame('23514', $state);
    }

    /** @return array<string, string> */
    private function event(User $recipient, string $kind): array
    {
        return ['id' => (string) Str::uuid(), 'event_id' => (string) Str::uuid(), 'recipient_id' => $recipient->id,
            'kind' => $kind, 'created_at' => '2026-10-11 19:00:00+00'];
    }
}

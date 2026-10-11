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

final class ResolutionNotificationMigrationTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_reverse_and_upgrade_preserve_other_installed_kinds(): void
    {
        $migration = require database_path('migrations/2026_10_11_210100_b19_resolution_notification_events.php');
        $recipient = User::factory()->verified()->create();
        foreach (['notification_outbox', 'internal_notifications'] as $table) {
            $check = DB::scalar('SELECT pg_get_expr(conbin, conrelid) FROM pg_constraint WHERE conrelid = CAST(? AS regclass) AND conname = ?', [$table, $table.'_kind']);
            $this->assertIsString($check);
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_kind");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_kind CHECK (({$check}) OR kind = 'capsule.version.published')");
            foreach (['profile.moderated', 'comment.created', 'proposal.created', 'capsule.version.published'] as $kind) {
                DB::table($table)->insert($this->event($recipient, $kind));
            }
        }
        foreach ([1, 2] as $iteration) {
            $migration->down();
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                $this->assertDatabaseCount($table, 4);
            }
            $migration->up();
            foreach (['notification_outbox', 'internal_notifications'] as $table) {
                $event = $this->event($recipient, 'resolution.accepted');
                DB::table($table)->insert($event);
                $this->assertDatabaseHas($table, $event);
                DB::table($table)->where('id', $event['id'])->delete();
            }
        }
    }

    #[DataProvider('notificationTables')]
    public function test_reverse_refuses_existing_resolution_events_without_discarding_them(string $table): void
    {
        $recipient = User::factory()->verified()->create();
        $event = $this->event($recipient, 'resolution.accepted');
        DB::table($table)->insert($event);
        $migration = require database_path('migrations/2026_10_11_210100_b19_resolution_notification_events.php');
        try {
            $migration->down();
            $this->fail('Les événements de résolutions doivent être conservés.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Retour B19 refusé : événements de résolutions à conserver.', $exception->getMessage());
        }
        $this->assertDatabaseHas($table, $event);
        foreach (['notification_outbox', 'internal_notifications'] as $other) {
            $next = $this->event($recipient, 'resolution.accepted');
            DB::table($other)->insert($next);
            $this->assertDatabaseHas($other, $next);
        }
    }

    #[DataProvider('notificationTables')]
    public function test_reverse_removes_only_the_new_kind(string $table): void
    {
        $recipient = User::factory()->verified()->create();
        $migration = require database_path('migrations/2026_10_11_210100_b19_resolution_notification_events.php');
        $migration->down();
        DB::table($table)->insert($this->event($recipient, 'comment.created'));
        $this->expectException(QueryException::class);
        DB::table($table)->insert($this->event($recipient, 'resolution.accepted'));
    }

    /** @return iterable<string, array{string}> */
    public static function notificationTables(): iterable
    {
        yield 'outbox' => ['notification_outbox'];
        yield 'internal' => ['internal_notifications'];
    }

    /** @return array<string, string> */
    private function event(User $recipient, string $kind): array
    {
        return ['id' => (string) Str::uuid(), 'event_id' => (string) Str::uuid(), 'recipient_id' => $recipient->id, 'kind' => $kind, 'created_at' => '2026-10-11 19:00:00+00'];
    }
}

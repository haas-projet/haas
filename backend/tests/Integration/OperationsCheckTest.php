<?php

namespace Tests\Integration;

use App\Data\Notifications\NotificationEvent;
use App\Models\User;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

final class OperationsCheckTest extends PostgresTestCase
{
    use DatabaseMigrations;

    public function test_health_reports_late_work_without_exposing_content(): void
    {
        $this->assertSame(0, Artisan::call('ops:check'));
        $this->assertSame('ok', json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['status']);
        $user = User::factory()->verified()->create();
        DB::transaction(fn () => app(NotificationOutbox::class)->record(new NotificationEvent((string) Str::uuid(), $user->id, 'profile.moderated')));
        DB::table('notification_outbox')->update(['created_at' => now()->subMinutes(6)]);
        $this->assertSame(1, Artisan::call('ops:check'));
        $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['status' => 'attention', 'database' => 'ok', 'failed_jobs' => 0, 'late_notifications' => 1, 'late_account_mail' => 0], $output);
        $this->assertStringNotContainsString($user->email, Artisan::output());
        app(NotificationOutbox::class)->deliver();
        $this->assertSame(0, Artisan::call('ops:check'));
    }

    public function test_delayed_or_other_queue_work_is_not_mistaken_for_late_account_mail(): void
    {
        $now = now()->getTimestamp();
        DB::table('jobs')->insert([
            ['queue' => 'account-mail', 'payload' => 'contenu privé fictif', 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now + 600, 'created_at' => $now - 600],
            ['queue' => 'other', 'payload' => 'autre file fictive', 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now - 600, 'created_at' => $now - 600],
        ]);
        $this->assertSame(0, Artisan::call('ops:check'));
        DB::table('jobs')->where('queue', 'account-mail')->update(['available_at' => $now - 600]);
        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'account-mail', 'payload' => 'contenu privé fictif', 'exception' => 'erreur privée fictive', 'failed_at' => now()]);
        $this->assertSame(1, Artisan::call('ops:check'));
        $this->assertSame(['status' => 'attention', 'database' => 'ok', 'failed_jobs' => 1, 'late_notifications' => 0, 'late_account_mail' => 1], json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_missing_storage_fails_closed_with_only_a_generic_message(): void
    {
        DB::statement('ALTER TABLE notification_outbox RENAME TO notification_outbox_unavailable');
        try {
            $this->assertSame(1, Artisan::call('ops:check'));
            $this->assertSame('{"status":"unavailable"}', trim(Artisan::output()));
        } finally {
            DB::statement('ALTER TABLE notification_outbox_unavailable RENAME TO notification_outbox');
        }
    }
}

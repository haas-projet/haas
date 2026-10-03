<?php

namespace Tests\Integration;

use App\Data\Notifications\NotificationEvent;
use App\Exceptions\NotificationStorageFailed;
use App\Models\User;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class InternalNotificationsTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_outbox_is_invisible_before_commit_and_rollback_leaves_no_event(): void
    {
        $user = User::factory()->verified()->create();
        $event = new NotificationEvent((string) Str::uuid(), $user->id, 'profile.moderated');
        config(['database.connections.notification_observer' => config('database.connections.pgsql')]);
        DB::beginTransaction();
        try {
            app(NotificationOutbox::class)->record($event);
            $this->assertSame(0, DB::connection('notification_observer')->table('notification_outbox')->count());
            try {
                app(NotificationOutbox::class)->deliver();
                $this->fail('Pas de livraison avant commit.');
            } catch (LogicException) {
                $this->assertDatabaseCount('internal_notifications', 0);
            }
        } finally {
            DB::rollBack();
            DB::disconnect('notification_observer');
        }
        $this->assertDatabaseCount('notification_outbox', 0);
        DB::transaction(fn () => app(NotificationOutbox::class)->record($event));
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        $this->assertDatabaseCount('internal_notifications', 1);
    }

    public function test_delivery_failure_retains_committed_intention_and_retry_is_unique(): void
    {
        $user = User::factory()->verified()->create();
        $event = new NotificationEvent((string) Str::uuid(), $user->id, 'profile.moderated');
        DB::transaction(function () use ($event, $user): void {
            $user->profile()->create(['bio' => 'Écriture métier fictive conservée']);
            app(NotificationOutbox::class)->record($event);
            app(NotificationOutbox::class)->record($event);
        });
        DB::statement('ALTER TABLE internal_notifications ADD CONSTRAINT b29_failure CHECK (false)');
        try {
            app(NotificationOutbox::class)->deliver();
            $this->fail('Échec de livraison attendu.');
        } catch (NotificationStorageFailed $exception) {
            $this->assertNull($exception->getPrevious());
            $this->assertDatabaseCount('profiles', 1);
            $this->assertDatabaseHas('notification_outbox', ['event_id' => $event->eventId, 'delivered_at' => null]);
        } finally {
            DB::statement('ALTER TABLE internal_notifications DROP CONSTRAINT b29_failure');
        }
        $this->assertSame(1, app(NotificationOutbox::class)->deliver());
        DB::transaction(fn () => app(NotificationOutbox::class)->record($event));
        $this->assertSame(0, app(NotificationOutbox::class)->deliver());
        $this->assertDatabaseCount('internal_notifications', 1);
        $row = DB::table('internal_notifications')->sole();
        $this->assertStringNotContainsString('Écriture métier', json_encode($row, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($user->email, json_encode($row, JSON_THROW_ON_ERROR));
    }

    public function test_private_paginated_inbox_read_unread_and_csrf(): void
    {
        $owner = User::factory()->verified()->create();
        $other = User::factory()->verified()->create(['role' => 'admin']);
        foreach ([$owner, $owner, $other] as $user) {
            DB::transaction(fn () => app(NotificationOutbox::class)->record(new NotificationEvent((string) Str::uuid(), $user->id, 'profile.moderated')));
        }
        app(NotificationOutbox::class)->deliver();
        $this->browserRequest('GET', '/api/v1/notifications')->assertUnauthorized();
        $this->login($owner);
        $list = $this->browserRequest('GET', '/api/v1/notifications?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('unread_count', 2);
        $id = $list->json('data.0.id');
        $path = '/api/v1/notifications/'.$id;
        $this->assertEqualsCanonicalizing(['id', 'kind', 'message', 'target_path', 'read_at', 'created_at'], array_keys($list->json('data.0')));
        $this->browserRequest('PATCH', $path, ['read' => true], sendXsrf: false)->assertStatus(419);
        $this->browserRequest('PATCH', $path, ['read' => true, 'recipient_id' => $other->id])->assertUnprocessable();
        $this->browserRequest('PATCH', $path, ['read' => 'false'])->assertUnprocessable();
        $first = $this->browserRequest('PATCH', $path, ['read' => true])->assertOk()->json('data.read_at');
        $this->travel(1)->minute();
        $this->browserRequest('PATCH', $path, ['read' => true])->assertOk()->assertJsonPath('data.read_at', $first);
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('unread_count', 1);
        $this->browserRequest('PATCH', $path, ['read' => false])->assertOk()->assertJsonPath('data.read_at', null);
        $this->login($other);
        $this->browserRequest('PATCH', $path, ['read' => true])->assertNotFound();
        $this->browserRequest('GET', '/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1);
    }

    private function login(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }
}

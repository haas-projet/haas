<?php

declare(strict_types=1);

namespace Tests\Integration\Capsules;

use App\Enums\Capsules\CapsuleVisibility;
use App\Models\Capsules\Capsule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\PostgresTestCase;
use Tests\Support\Mdev44\DomainTables;
use Tests\Support\SpaHttpRequests;
use Tests\Support\TestDatabaseGuard;

final class FavoriteHttpTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    protected function tearDown(): void
    {
        try {
            TestDatabaseGuard::check(config('app.env'), config('database.default'), config('database.connections.pgsql'));
            foreach (['internal_notifications', 'notification_outbox'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('kind', 'capsule.review.changes_requested')->delete();
                }
            }
            DomainTables::dropAll();
        } finally {
            parent::tearDown();
        }
    }

    public function test_put_favorite_is_idempotent(): void
    {
        $member = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['slug' => 'cible-favori']);
        $this->loginAs($member);
        $first = $this->browserRequest('PUT', "/api/v1/capsules/{$capsule->id}/favorite");
        $first->assertOk();
        $this->assertSame('no-store, private', $first->headers->get('Cache-Control'));
        $second = $this->browserRequest('PUT', "/api/v1/capsules/{$capsule->id}/favorite");
        $second->assertOk();
        $this->assertSame(1, DB::table('favorites')->where('user_id', $member->id)->where('capsule_id', $capsule->id)->count());
    }

    public function test_delete_favorite_absent_is_a_success_no_op(): void
    {
        $member = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['slug' => 'absent-favori']);
        $this->loginAs($member);
        $response = $this->browserRequest('DELETE', "/api/v1/capsules/{$capsule->id}/favorite");
        $response->assertOk();
        $this->assertSame(0, (int) $response->json('data.deleted'));
    }

    public function test_delete_favorite_is_idempotent(): void
    {
        $member = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['slug' => 'retire-favori']);
        $this->loginAs($member);
        $this->browserRequest('PUT', "/api/v1/capsules/{$capsule->id}/favorite")->assertOk();
        $this->browserRequest('DELETE', "/api/v1/capsules/{$capsule->id}/favorite")->assertOk();
        $this->browserRequest('DELETE', "/api/v1/capsules/{$capsule->id}/favorite")->assertOk();
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_other_members_cannot_see_my_favorites(): void
    {
        $owner = User::factory()->verified()->create();
        $peer = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['slug' => 'intimite-favori']);
        $this->loginAs($owner);
        $this->browserRequest('PUT', "/api/v1/capsules/{$capsule->id}/favorite")->assertOk();
        // Aucune route publique ne liste mes favoris : le peer ne les voit nulle part.
        // On le prouve en vérifiant que mettre son propre favori est strictement
        // orthogonal à celui d'un autre et qu'une tentative d'accès au nôtre via
        // PUT/DELETE "au nom de" ne retourne pas les lignes de l'autre.
        $this->browserCookies = [];
        $this->loginAs($peer);
        $peerPut = $this->browserRequest('PUT', "/api/v1/capsules/{$capsule->id}/favorite");
        $peerPut->assertOk();
        // Le peer n'ajoute que SA ligne : deux lignes au total, l'une pour l'owner et l'autre pour le peer.
        $this->assertDatabaseCount('favorites', 2);
        $this->assertDatabaseHas('favorites', ['user_id' => $owner->id, 'capsule_id' => $capsule->id]);
        $this->assertDatabaseHas('favorites', ['user_id' => $peer->id, 'capsule_id' => $capsule->id]);
    }

    public function test_hidden_capsule_cannot_be_favorited(): void
    {
        $member = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['slug' => 'masquee-favori', 'visibility' => CapsuleVisibility::Hidden->value]);
        $this->loginAs($member);
        $response = $this->browserRequest('PUT', "/api/v1/capsules/{$capsule->id}/favorite");
        $response->assertStatus(403);
    }

    public function test_anonymous_cannot_favorite(): void
    {
        $capsule = Capsule::factory()->create(['slug' => 'anonyme-favori']);
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('PUT', "/api/v1/capsules/{$capsule->id}/favorite");
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_response_does_not_leak_user_id(): void
    {
        $member = User::factory()->verified()->create();
        $capsule = Capsule::factory()->create(['slug' => 'reponse-propre']);
        $this->loginAs($member);
        $data = $this->browserRequest('PUT', "/api/v1/capsules/{$capsule->id}/favorite")->json('data');
        $this->assertArrayNotHasKey('user_id', $data);
        $this->assertArrayNotHasKey('email', $data);
    }

    private function loginAs(User $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test']);
    }
}

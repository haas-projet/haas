<?php

namespace Tests\Integration;

use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\Profile;
use App\Models\Technology;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PostgresTestCase;

final class IdentityDatabaseTest extends PostgresTestCase
{
    use RefreshDatabase;

    public function test_defaults_casts_relations_and_private_fields_on_postgresql(): void
    {
        $user = User::factory()->create(['handle' => 'Awa', 'email' => ' AWA@Example.test '])->refresh();
        $this->assertSame('awa@example.test', $user->email);
        $this->assertSame(Role::Member, $user->role);
        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertFalse($user->is_demo);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('mot-de-passe-de-test', $user->password));

        $profile = $user->profile()->save(new Profile(['bio' => 'Un profil de test.']));
        $technology = Technology::factory()->create(['slug' => ' LARAVEL ', 'name' => 'Laravel']);
        $user->technologies()->attach($technology);
        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertInstanceOf(User::class, $profile->user);
        $this->assertInstanceOf(Profile::class, $user->profile);
        $this->assertSame($user->id, $profile->user->id);
        $this->assertSame('fr', $user->profile->primary_language);
        $this->assertNull($user->profile->country);
        $this->assertSame('laravel', $user->technologies->sole()->slug);
        $this->assertTrue(Str::isUuid($technology->id));
        $this->assertSame($user->id, $technology->users->sole()->id);

        $user->forceFill(['role' => Role::Moderator, 'status' => AccountStatus::Suspended, 'is_demo' => true])->save();
        $user->markEmailAsVerified();
        $user->refresh();
        $this->assertSame(Role::Moderator, $user->role);
        $this->assertSame(AccountStatus::Suspended, $user->status);
        $this->assertTrue($user->is_demo);
        $this->assertInstanceOf(CarbonImmutable::class, $user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
        foreach (['email', 'password', 'remember_token', 'role', 'status', 'email_verified_at', 'is_demo', 'name'] as $field) {
            $this->assertArrayNotHasKey($field, $user->toArray());
        }
    }

    public function test_raw_inserts_also_receive_safe_defaults(): void
    {
        $id = (string) Str::uuid();
        DB::table('users')->insert(['id' => $id, 'handle' => 'MembreSQL', 'email' => 'sql@example.test', 'password' => 'hash-de-fixture']);
        $user = User::findOrFail($id);
        $this->assertSame(Role::Member, $user->role);
        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertFalse($user->is_demo);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    #[DataProvider('invalidAccounts')]
    public function test_database_rejects_invalid_identity_values(string $field, mixed $value, string $sqlState): void
    {
        $user = User::factory()->create();
        $this->assertSqlFailure(fn () => DB::table('users')->where('id', $user->id)->update([$field => $value]), $sqlState);
        $this->assertDatabaseCount('users', 1);
    }

    /** @return iterable<string, array{string, mixed, string}> */
    public static function invalidAccounts(): iterable
    {
        yield 'role' => ['role', 'owner', '23514'];
        yield 'status' => ['status', 'deleted', '23514'];
        yield 'missing role' => ['role', null, '23502'];
        yield 'missing status' => ['status', null, '23502'];
        yield 'missing demo flag' => ['is_demo', null, '23502'];
        yield 'missing handle' => ['handle', null, '23502'];
        yield 'short handle' => ['handle', 'ab', '23514'];
        yield 'long handle' => ['handle', str_repeat('a', 31), '22001'];
        yield 'untrimmed handle' => ['handle', ' awa ', '23514'];
        yield 'control character' => ['handle', "a\nb", '23514'];
        yield 'uppercase email' => ['email', 'AWA@example.test', '23514'];
        yield 'untrimmed email' => ['email', ' awa@example.test ', '23514'];
        yield 'empty email' => ['email', '', '23514'];
    }

    public function test_handles_and_normalized_emails_are_unique(): void
    {
        User::factory()->create(['handle' => 'Awa', 'email' => 'awa@example.test']);
        $this->assertSqlFailure(fn () => User::factory()->create(['handle' => 'awa']), '23505');
        $this->assertSqlFailure(fn () => User::factory()->create(['email' => ' AWA@EXAMPLE.TEST ']), '23505');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_profile_and_technology_constraints_and_deletion_rules(): void
    {
        $user = User::factory()->create();
        Profile::factory()->for($user)->create();
        $technology = Technology::factory()->create(['slug' => 'php', 'name' => 'PHP']);
        $user->technologies()->attach($technology);

        $this->assertSqlFailure(fn () => Profile::factory()->for($user)->create(), '23505');
        $this->assertSqlFailure(fn () => DB::table('profiles')->insert(['user_id' => (string) Str::uuid()]), '23503');
        $this->assertSqlFailure(fn () => $user->technologies()->attach($technology), '23505');
        $this->assertSqlFailure(fn () => $user->technologies()->attach((string) Str::uuid()), '23503');
        $this->assertSqlFailure(fn () => Technology::factory()->create(['slug' => 'PHP']), '23505');
        $this->assertSqlFailure(fn () => DB::table('technologies')->where('id', $technology->id)->update(['slug' => 'BAD slug']), '23514');
        $this->assertSqlFailure(fn () => $technology->delete(), '23503');
        $this->assertSqlFailure(fn () => DB::table('profiles')->where('user_id', $user->id)->update(['bio' => str_repeat('a', 501)]), '22001');

        $user->delete();
        $this->assertDatabaseCount('profiles', 0);
        $this->assertDatabaseCount('user_technologies', 0);
        $this->assertDatabaseHas('technologies', ['id' => $technology->id]);
    }

    public function test_privileged_registration_payload_never_creates_an_account(): void
    {
        $payload = ['handle' => 'Awa', 'email' => 'awa@example.test', 'password' => 'mot-de-passe-de-test', 'role' => 'admin', 'author_id' => (string) Str::uuid(), 'email_verified_at' => now()->toISOString()];
        $this->postJson('/register', $payload)->assertClientError();
        $this->postJson('/api/v1/register', $payload)->assertClientError();
        $this->assertDatabaseCount('users', 0);
    }

    /** @param Closure(): mixed $operation */
    private function assertSqlFailure(Closure $operation, string $sqlState): void
    {
        try {
            DB::transaction($operation);
            $this->fail('La contrainte PostgreSQL devait refuser cette écriture.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->getCode());
        }
    }
}

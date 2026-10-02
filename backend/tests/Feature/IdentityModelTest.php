<?php

namespace Tests\Feature;

use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class IdentityModelTest extends TestCase
{
    public function test_account_defaults_and_normalization_are_safe(): void
    {
        $user = new User(['handle' => '  Awa  ', 'email' => '  AWA@Example.test  ']);

        $this->assertSame('Awa', $user->handle);
        $this->assertSame('awa@example.test', $user->email);
        $this->assertSame(Role::Member, $user->role);
        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertFalse($user->is_demo);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertArrayNotHasKey('email', $user->toArray());
        $this->assertArrayNotHasKey('role', $user->toArray());
        $this->assertArrayNotHasKey('status', $user->toArray());
    }

    #[DataProvider('protectedFields')]
    public function test_mass_assignment_rejects_server_owned_fields(string $field): void
    {
        $this->expectException(MassAssignmentException::class);
        (new User)->fill(['handle' => 'Awa', $field => null]);
    }

    /** @return iterable<string, array{string}> */
    public static function protectedFields(): iterable
    {
        foreach (['id', 'role', 'admin', 'is_admin', 'status', 'author_id', 'email_verified_at', 'verified_at', 'is_demo', 'remember_token', 'name'] as $field) {
            yield $field => [$field];
        }
    }

    public function test_role_and_status_values_match_the_shared_contract(): void
    {
        $contract = Yaml::parseFile(base_path('../docs/api/openapi/identity.yaml'));
        $this->assertSame(array_column(Role::cases(), 'value'), $contract['components']['schemas']['AccountRole']['enum']);
        $this->assertSame(array_column(AccountStatus::cases(), 'value'), $contract['components']['schemas']['AccountStatus']['enum']);
    }
}

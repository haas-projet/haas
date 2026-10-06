<?php

namespace Tests\Feature;

use App\Enums\Identity\AccountStatus;
use App\Enums\Identity\Role;
use App\Models\User;
use App\Support\Identity\MemberAccess;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AccountAuthorizationTest extends TestCase
{
    #[DataProvider('actors')]
    public function test_account_policy_denies_foreign_ownership_for_every_role(Role $role, bool $verified, AccountStatus $status): void
    {
        $actor = $this->member($role, $verified, $status);
        $other = $this->member(Role::Member, true, AccountStatus::Active);
        $other->id = '7c76ba53-cccb-4a09-8549-e6a7ff5d7484';
        $gate = Gate::forUser($actor);
        $active = $status === AccountStatus::Active;
        $eligible = $active && $verified;

        foreach (['view', 'manageAccountMail', 'updateProfile'] as $ability) {
            $this->assertFalse($gate->allows($ability, $other), $role->value.' '.$ability);
        }
        $this->assertSame($active, $gate->allows('view', $actor));
        $this->assertSame($active, $gate->allows('manageAccountMail', $actor));
        $this->assertSame($eligible, $gate->allows('updateProfile', $actor));
        $this->assertSame($eligible, $gate->allows('participate', User::class));
        $this->assertSame($eligible && $role !== Role::Member, $gate->allows('moderate', User::class));
        $this->assertSame($eligible && $role === Role::Admin, $gate->allows('administer', User::class));
        $this->assertFalse($gate->allows('undefined-command', $other));
        $access = new MemberAccess;
        $this->assertFalse($access->owns($actor, $other->id));
        $this->assertSame($eligible, $access->owns($actor, $actor->id));
    }

    /** @return iterable<string, array{Role, bool, AccountStatus}> */
    public static function actors(): iterable
    {
        foreach (Role::cases() as $role) {
            foreach ([false, true] as $verified) {
                foreach (AccountStatus::cases() as $status) {
                    yield $role->value.' '.(int) $verified.' '.$status->value => [$role, $verified, $status];
                }
            }
        }
    }

    public function test_guest_cannot_read_private_account_or_use_any_member_capability(): void
    {
        $user = $this->member(Role::Member, true, AccountStatus::Active);
        foreach (['view', 'updateProfile', 'manageAccountMail', 'participate', 'moderate', 'administer'] as $ability) {
            $this->assertFalse(Gate::allows($ability, $user));
        }
        $this->assertFalse((new MemberAccess)->owns(null, $user->id));
        $this->assertFalse((new MemberAccess)->verified(null));
    }

    private function member(Role $role, bool $verified, AccountStatus $status): User
    {
        // Format explicite : cette matrice en mémoire n'initialise aucune connexion SQL.
        return (new User)->setDateFormat('Y-m-d H:i:s')->forceFill([
            'id' => 'b0fbc5aa-662e-4cac-83eb-a21259c98e03', 'role' => $role, 'status' => $status,
            'email_verified_at' => $verified ? '2026-10-02 12:00:00' : null,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\AccessGuard;
use Igne\Permissions\Contracts\ResolvesRolePermissions;
use Igne\Permissions\Exceptions\ConflictingAccessException;
use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\PermissionAction;
use Igne\Permissions\Tests\Fixtures\TestPlainUser;
use Igne\Permissions\Tests\Fixtures\TestPost;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

/**
 * The package in an app without Spatie: Spatie's provider is not registered, and an app callback
 * grants the names a {@see TestPlainUser} holds.
 */
final class WithoutSpatieTest extends PermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(static fn (Authorizable $user, string $ability): ?bool => $user instanceof TestPlainUser && \in_array($ability, $user->grantedPermissions, true) ? true : null);
    }

    public function test_no_role_lookup_is_bound(): void
    {
        $this->assertFalse(app()->bound(ResolvesRolePermissions::class));
    }

    public function test_the_guard_checks_permissions_through_the_gate(): void
    {
        $user = $this->user('portal:access');

        $this->assertTrue($this->guard()->wouldConflict($user, ['guest:access']));
        $this->assertFalse($this->guard()->wouldConflict($user, ['backend:access']));

        $this->expectException(ConflictingAccessException::class);

        $this->guard()->assertCanGrant($user, ['guest:access']);
    }

    public function test_permissions_may_be_any_model_with_a_name(): void
    {
        $permission = new class extends Model
        {
            protected $guarded = [];
        };
        $permission->setAttribute('name', 'guest:access');

        $this->assertTrue($this->guard()->wouldConflict($this->user('portal:access'), [$permission]));
    }

    public function test_role_checks_without_a_role_lookup_throw(): void
    {
        $this->expectException(PermissionException::class);

        $this->guard()->wouldRoleConflict($this->user('portal:access'), ['guest']);
    }

    public function test_role_checks_use_the_bound_role_lookup(): void
    {
        app()->bind(ResolvesRolePermissions::class, static fn (): ResolvesRolePermissions => new class implements ResolvesRolePermissions
        {
            public function permissionNames(Authorizable&Model $user, array $roles): array
            {
                return \in_array('guest', $roles, true) ? ['guest:access'] : [];
            }
        });
        $user = $this->user('portal:access');

        $this->assertTrue($this->guard()->wouldRoleConflict($user, ['guest']));
        $this->assertFalse($this->guard()->wouldRoleConflict($user, ['editor']));
    }

    public function test_a_name_without_in_resolves_the_users_space(): void
    {
        $holder = $this->user(permission()->in(TestSpace::PORTAL)->update(TestPost::class));

        $holder->space = TestSpace::PORTAL;
        $this->assertTrue($holder->can(permission()->update(TestPost::class)));

        $holder->space = TestSpace::ADMIN;
        $this->assertFalse($holder->can(permission()->update(TestPost::class)));
    }

    public function test_the_gate_rules_apply(): void
    {
        Config::set('permissions.implied_actions', [PermissionAction::UPDATE->value => [PermissionAction::VIEW]]);
        Config::set('permissions.requires_access', true);
        $update = permission()->in(TestSpace::PORTAL)->update(TestPost::class);

        $holder = $this->user($update, 'portal:access');
        $holder->space = TestSpace::PORTAL;
        $withoutAccess = $this->user($update);
        $withoutAccess->space = TestSpace::PORTAL;

        $this->assertTrue($holder->can(permission()->view(TestPost::class)));
        $this->assertFalse($withoutAccess->can(permission()->update(TestPost::class)));
    }

    private function user(string ...$permissions): TestPlainUser
    {
        $user = new TestPlainUser;
        $user->grantedPermissions = array_values($permissions);

        return $user;
    }

    private function guard(): AccessGuard
    {
        return app(AccessGuard::class);
    }
}

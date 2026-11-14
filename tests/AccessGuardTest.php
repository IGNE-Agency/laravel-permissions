<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use ArrayIterator;
use Igne\Permissions\AccessGuard;
use Igne\Permissions\Exceptions\ConflictingAccessException;
use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\PermissionRegistry;
use Igne\Permissions\Tests\Fixtures\TestPermissionName;
use Igne\Permissions\Tests\Fixtures\TestRoleName;
use Igne\Permissions\Tests\Fixtures\TestSettingPermission;
use Igne\Permissions\Tests\Fixtures\TestSpace;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class AccessGuardTest extends SpatieTestCase
{
    public function test_the_guard_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(AccessGuard::class, app(AccessGuard::class));
    }

    public function test_exclusive_spaces_come_from_the_space_enums_that_declare_them(): void
    {
        $this->assertSame([TestSpace::PORTAL, TestSpace::GUEST], PermissionRegistry::fromConfig()->exclusiveSpaces());

        Config::set('permissions.subjects', [TestSettingPermission::class]);

        $this->assertSame([], PermissionRegistry::fromConfig()->exclusiveSpaces());
    }

    public function test_granting_access_to_a_second_exclusive_space_throws(): void
    {
        $user = $this->user('portal:access');

        $this->assertTrue($this->guard()->wouldConflict($user, ['guest:access']));

        $this->expectException(ConflictingAccessException::class);

        $this->guard()->assertCanGrant($user, ['guest:access']);
    }

    public function test_granting_both_exclusive_spaces_at_once_throws(): void
    {
        $user = $this->user();

        $this->expectException(ConflictingAccessException::class);

        $this->guard()->assertCanGrant($user, ['backend:access', 'portal:access', 'guest:access']);
    }

    public function test_the_exception_carries_both_spaces(): void
    {
        $user = $this->user('portal:access');

        try {
            $this->guard()->assertCanGrant($user, ['guest:access']);
            $this->fail('Expected a ConflictingAccessException.');
        } catch (ConflictingAccessException $exception) {
            $this->assertSame(TestSpace::PORTAL, $exception->first);
            $this->assertSame(TestSpace::GUEST, $exception->second);
            $this->assertStringContainsString('TestSpace::PORTAL', $exception->getMessage());
            $this->assertStringContainsString('TestSpace::GUEST', $exception->getMessage());
        }
    }

    public function test_the_same_space_or_a_non_exclusive_space_does_not_conflict(): void
    {
        $user = $this->user('portal:access');

        $this->guard()->assertCanGrant($user, ['portal:access', 'backend:access', 'backend:manageUsers']);

        $this->assertFalse($this->guard()->wouldConflict($user, ['backend:access']));
        $this->assertFalse($this->guard()->wouldConflict($user, []));
    }

    public function test_revoked_access_does_not_count(): void
    {
        $user = $this->user('portal:access');
        $user->revokedPermissions = ['portal:access'];

        $this->guard()->assertCanGrant($user, ['guest:access']);

        $this->assertFalse($this->guard()->wouldConflict($user, ['guest:access']));
    }

    public function test_assigning_a_role_that_carries_conflicting_access_throws(): void
    {
        Role::create(['name' => 'guest', 'guard_name' => 'web'])->givePermissionTo('guest:access');
        $user = $this->user('portal:access');

        $this->assertTrue($this->guard()->wouldRoleConflict($user, ['guest']));

        $this->expectException(ConflictingAccessException::class);

        $this->guard()->assertCanAssignRoles($user, ['guest']);
    }

    public function test_a_role_without_exclusive_access_does_not_conflict(): void
    {
        Role::create(['name' => 'editor', 'guard_name' => 'web'])->givePermissionTo('backend:access', 'backend:manageUsers');
        $user = $this->user('portal:access');

        $this->guard()->assertCanAssignRoles($user, ['editor']);

        $this->assertFalse($this->guard()->wouldRoleConflict($user, ['editor']));
    }

    public function test_a_role_that_does_not_exist_is_skipped(): void
    {
        $user = $this->user('portal:access');

        $this->guard()->assertCanAssignRoles($user, ['nobody']);

        $this->assertFalse($this->guard()->wouldRoleConflict($user, ['nobody']));
    }

    public function test_a_role_of_another_guard_is_ignored(): void
    {
        Permission::create(['name' => 'guest:access', 'guard_name' => 'api']);
        Role::create(['name' => 'guest', 'guard_name' => 'api'])->givePermissionTo('guest:access');
        $user = $this->user('portal:access');

        $this->guard()->assertCanAssignRoles($user, ['guest']);

        $this->assertFalse($this->guard()->wouldRoleConflict($user, ['guest']));
    }

    public function test_permissions_may_be_names_enums_models_and_nested_iterables(): void
    {
        $user = $this->user('portal:access');
        $model = Permission::findByName('guest:access', 'web');

        $this->assertTrue($this->guard()->wouldConflict($user, [TestPermissionName::GUEST_ACCESS]));
        $this->assertTrue($this->guard()->wouldConflict($user, [[$model]]));
        $this->assertTrue($this->guard()->wouldConflict($user, new ArrayIterator([collect([$model])])));
    }

    public function test_roles_may_be_names_enums_models_and_nested_iterables(): void
    {
        $role = Role::create(['name' => 'guest', 'guard_name' => 'web']);
        $role->givePermissionTo('guest:access');
        $user = $this->user('portal:access');

        $this->assertTrue($this->guard()->wouldRoleConflict($user, [TestRoleName::GUEST]));
        $this->assertTrue($this->guard()->wouldRoleConflict($user, [[$role]]));
        $this->assertTrue($this->guard()->wouldRoleConflict($user, new ArrayIterator([collect([$role])])));
        $this->assertFalse($this->guard()->wouldRoleConflict($user, [TestRoleName::EDITOR]));
    }

    public function test_an_unsupported_permission_input_throws(): void
    {
        $user = $this->user();

        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('permissions must be names, backed enums or Spatie permission models');

        $this->guard()->assertCanGrant($user, [42]);
    }

    public function test_an_unsupported_role_input_throws(): void
    {
        $user = $this->user();

        $this->expectException(PermissionException::class);
        $this->expectExceptionMessage('roles must be names, backed enums or Spatie role models');

        $this->guard()->assertCanAssignRoles($user, [42]);
    }

    private function guard(): AccessGuard
    {
        return app(AccessGuard::class);
    }
}

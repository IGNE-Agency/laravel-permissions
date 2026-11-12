<?php

declare(strict_types=1);

namespace Igne\Permissions;

use BackedEnum;
use Igne\Permissions\Contracts\HasExclusiveAccess;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Exceptions\ConflictingAccessException;
use Igne\Permissions\Exceptions\PermissionException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;
use Traversable;

/**
 * Enforces that a user never effectively holds the "access" permissions of two exclusive spaces at
 * once (see {@see HasExclusiveAccess}). Effective access is evaluated through the user's can(), so
 * existing role grants and per-user revocations are respected.
 *
 * Resolve it from the container: the registry is rebuilt from config on every resolution.
 */
final class AccessGuard
{
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * Throw if granting the permissions would leave the user with conflicting access.
     *
     * @param  iterable<mixed>  $permissions  Permission names, backed enums, Spatie permission models, or nested iterables of them.
     */
    public function assertCanGrant(Authorizable&Model $user, iterable $permissions): void
    {
        $this->assertNoConflict($user, $this->permissionNames($permissions));
    }

    /**
     * Throw if assigning the roles would leave the user with conflicting access.
     *
     * @param  iterable<mixed>  $roles  Role names, backed enums, Spatie role models, or nested iterables of them.
     */
    public function assertCanAssignRoles(Authorizable&Model $user, iterable $roles): void
    {
        $this->assertNoConflict($user, $this->rolePermissionNames($user, $roles));
    }

    /**
     * @param  iterable<mixed>  $permissions
     */
    public function wouldConflict(Authorizable&Model $user, iterable $permissions): bool
    {
        return count($this->resultingExclusiveSpaces($user, $this->permissionNames($permissions))) > 1;
    }

    /**
     * @param  iterable<mixed>  $roles
     */
    public function wouldRoleConflict(Authorizable&Model $user, iterable $roles): bool
    {
        return count($this->resultingExclusiveSpaces($user, $this->rolePermissionNames($user, $roles))) > 1;
    }

    /**
     * @param  list<string>  $names
     */
    private function assertNoConflict(Authorizable&Model $user, array $names): void
    {
        $spaces = $this->resultingExclusiveSpaces($user, $names);

        if (count($spaces) > 1) {
            throw ConflictingAccessException::between($spaces[0], $spaces[1]);
        }
    }

    /**
     * The exclusive spaces the user would hold access to after the grant: any space the grant
     * introduces, plus any the user already has effective access to.
     *
     * @param  list<string>  $names
     * @return list<SpaceEnum>
     */
    private function resultingExclusiveSpaces(Authorizable&Model $user, array $names): array
    {
        return array_values(array_filter(
            $this->registry->exclusiveSpaces(),
            fn (SpaceEnum $space): bool => in_array($this->registry->accessName($space), $names, true)
                || $user->can($this->registry->accessName($space)),
        ));
    }

    /**
     * The permission names carried by the roles, looked up by name within the user's guard. Roles
     * that do not exist are skipped.
     *
     * @param  iterable<mixed>  $roles
     * @return list<string>
     */
    private function rolePermissionNames(Authorizable&Model $user, iterable $roles): array
    {
        $roleClass = $this->registrar->getRoleClass();

        if (! is_a($roleClass, Model::class, true)) {
            throw PermissionException::invalidConfig('permission.models.role', 'must be an Eloquent model');
        }

        $guardName = Guard::getDefaultName($user);
        $names = [];

        foreach ($this->flatten($roles) as $role) {
            $roleModel = $roleClass::query()
                ->where('name', $this->roleName($role))
                ->where('guard_name', $guardName)
                ->first();

            if (! $roleModel instanceof RoleContract) {
                continue;
            }

            foreach ($roleModel->permissions()->get() as $permission) {
                $names[] = $this->permissionName($permission);
            }
        }

        return $names;
    }

    /**
     * @param  iterable<mixed>  $permissions
     * @return list<string>
     */
    private function permissionNames(iterable $permissions): array
    {
        $names = [];

        foreach ($this->flatten($permissions) as $permission) {
            $names[] = $this->permissionName($permission);
        }

        return $names;
    }

    private function permissionName(mixed $permission): string
    {
        return match (true) {
            is_string($permission) => $permission,
            $permission instanceof BackedEnum => (string) $permission->value,
            $permission instanceof PermissionContract => $permission->name,
            default => throw PermissionException::invalidSelection('permissions must be names, backed enums or Spatie permission models'),
        };
    }

    private function roleName(mixed $role): string
    {
        return match (true) {
            is_string($role) => $role,
            $role instanceof BackedEnum => (string) $role->value,
            $role instanceof RoleContract => $role->name,
            default => throw PermissionException::invalidSelection('roles must be names, backed enums or Spatie role models'),
        };
    }

    /**
     * Recursively flatten arrays and traversables into a single list of items.
     *
     * @param  iterable<mixed>  $items
     * @return list<mixed>
     */
    private function flatten(iterable $items): array
    {
        $flat = [];

        foreach ($items as $item) {
            if (is_array($item) || $item instanceof Traversable) {
                $flat = [...$flat, ...$this->flatten($item)];

                continue;
            }

            $flat[] = $item;
        }

        return $flat;
    }
}

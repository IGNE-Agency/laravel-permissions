<?php

declare(strict_types=1);

namespace Igne\Permissions;

use BackedEnum;
use Igne\Permissions\Contracts\HasExclusiveAccess;
use Igne\Permissions\Contracts\ResolvesRolePermissions;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Exceptions\ConflictingAccessException;
use Igne\Permissions\Exceptions\PermissionException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;

/**
 * Enforces that a user never effectively holds the "access" permissions of two exclusive spaces at
 * once (see {@see HasExclusiveAccess}). Effective access is evaluated through the user's can(), so
 * existing role grants and per-user revocations are respected.
 *
 * Resolve it from the container: the registry is rebuilt from config on every resolution, and the
 * role checks get the bound {@see ResolvesRolePermissions}, when there is one.
 */
final class AccessGuard
{
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly ?ResolvesRolePermissions $rolePermissions = null,
    ) {}

    /**
     * Throw if granting the permissions would leave the user with conflicting access.
     *
     * @param  iterable<mixed>  $permissions  Permission names, backed enums, models with a name, or nested iterables of them.
     */
    public function assertCanGrant(Authorizable&Model $user, iterable $permissions): void
    {
        $this->assertNoConflict($user, $this->permissionNames($permissions));
    }

    /**
     * Throw if assigning the roles would leave the user with conflicting access.
     *
     * @param  iterable<mixed>  $roles  Role names, backed enums, models with a name, or nested iterables of them.
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
        return \count($this->resultingExclusiveSpaces($user, $this->permissionNames($permissions))) > 1;
    }

    /**
     * @param  iterable<mixed>  $roles
     */
    public function wouldRoleConflict(Authorizable&Model $user, iterable $roles): bool
    {
        return \count($this->resultingExclusiveSpaces($user, $this->rolePermissionNames($user, $roles))) > 1;
    }

    /**
     * @param  list<string>  $names
     */
    private function assertNoConflict(Authorizable&Model $user, array $names): void
    {
        $spaces = $this->resultingExclusiveSpaces($user, $names);

        if (\count($spaces) > 1) {
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
            fn (SpaceEnum $space): bool => \in_array($this->registry->accessName($space), $names, true)
                || $user->can($this->registry->accessName($space)),
        ));
    }

    /**
     * The permission names carried by the roles, from the bound role lookup.
     *
     * @param  iterable<mixed>  $roles
     * @return list<string>
     */
    private function rolePermissionNames(Authorizable&Model $user, iterable $roles): array
    {
        if ($this->rolePermissions === null) {
            throw PermissionException::missingRolePermissions();
        }

        return $this->rolePermissions->permissionNames($user, array_map($this->roleName(...), $this->flatten($roles)));
    }

    /**
     * @param  iterable<mixed>  $permissions
     * @return list<string>
     */
    private function permissionNames(iterable $permissions): array
    {
        return array_map($this->permissionName(...), $this->flatten($permissions));
    }

    private function permissionName(mixed $permission): string
    {
        return self::itemName($permission)
            ?? throw PermissionException::invalidSelection('permissions must be names, backed enums or models with a name');
    }

    private function roleName(mixed $role): string
    {
        return self::itemName($role)
            ?? throw PermissionException::invalidSelection('roles must be names, backed enums or models with a name');
    }

    /**
     * The name of a string, a backed enum or a model with a string name attribute, such as
     * Spatie's permission and role models.
     */
    private static function itemName(mixed $item): ?string
    {
        $name = match (true) {
            $item instanceof BackedEnum => (string) $item->value,
            $item instanceof Model => $item->getAttribute('name'),
            default => $item,
        };

        return \is_string($name) ? $name : null;
    }

    /**
     * Recursively flatten arrays and traversables into a single list of items.
     *
     * @param  iterable<mixed>  $items
     * @return list<mixed>
     */
    private function flatten(iterable $items): array
    {
        return array_values(collect(\is_array($items) ? $items : iterator_to_array($items, false))
            ->flatMap(fn (mixed $item): array => is_iterable($item) ? $this->flatten($item) : [$item])
            ->all());
    }
}

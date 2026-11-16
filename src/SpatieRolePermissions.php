<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\ResolvesRolePermissions;
use Igne\Permissions\Exceptions\PermissionException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;

/**
 * The role lookup for apps on spatie/laravel-permission: the roles are read within the user's guard
 * in one query, together with their permissions.
 */
final readonly class SpatieRolePermissions implements ResolvesRolePermissions
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function permissionNames(Authorizable&Model $user, array $roles): array
    {
        $roleClass = $this->registrar->getRoleClass();

        if (! is_a($roleClass, Model::class, true)) {
            throw PermissionException::invalidConfig('permission.models.role', 'must be an Eloquent model');
        }

        return array_values($roleClass::query()
            ->whereIn('name', $roles)
            ->where('guard_name', Guard::getDefaultName($user))
            ->with('permissions')
            ->get()
            ->flatMap(self::loadedPermissionNames(...))
            ->all());
    }

    /**
     * @return list<string>
     */
    private static function loadedPermissionNames(Model $role): array
    {
        $permissions = $role->getRelation('permissions');

        return $permissions instanceof Collection
            ? array_values(array_filter($permissions->pluck('name')->all(), is_string(...)))
            : [];
    }
}

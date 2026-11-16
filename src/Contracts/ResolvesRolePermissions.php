<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;

/**
 * Looks up the permission names that roles carry, for the role checks of the access guard. The
 * package binds a Spatie version when spatie/laravel-permission is registered; an app that keeps
 * its roles elsewhere binds its own.
 */
interface ResolvesRolePermissions
{
    /**
     * @param  list<string>  $roles  Role names; a name that does not exist is skipped.
     * @return list<string>
     */
    public function permissionNames(Authorizable&Model $user, array $roles): array;
}

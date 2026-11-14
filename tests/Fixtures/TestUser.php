<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Contracts\HasPermissionSpace;
use Igne\Permissions\Contracts\SpaceEnum;
use Illuminate\Foundation\Auth\User;
use LogicException;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Traits\HasRoles;

/**
 * A Spatie-enabled user. Names listed in $revokedPermissions are denied by
 * hasPermissionTo() whatever the roles say, wrapping Spatie's method through the trait alias the
 * way an app with per-user revocations does, so every check through the Gate honours them.
 */
final class TestUser extends User implements HasPermissionSpace
{
    use HasRoles {
        hasPermissionTo as protected spatieHasPermissionTo;
    }

    protected $table = 'users';

    protected $guarded = [];

    /**
     * @var list<string>
     */
    public array $revokedPermissions = [];

    /**
     * @var SpaceEnum|list<SpaceEnum>|null
     */
    public SpaceEnum|array|null $space = null;

    /**
     * @return SpaceEnum|list<SpaceEnum>
     */
    public function permissionSpace(): SpaceEnum|array
    {
        if ($this->space === null) {
            throw new LogicException('The test did not give this user a space.');
        }

        return $this->space;
    }

    /**
     * @param  string|int|Permission|\BackedEnum  $permission
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        if (is_string($permission) && in_array($permission, $this->revokedPermissions, true)) {
            return false;
        }

        return $this->spatieHasPermissionTo($permission, $guardName);
    }
}

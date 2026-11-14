<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Traits\HasRoles;

/**
 * A Spatie-enabled user. Names listed in $revokedPermissions are denied by
 * hasPermissionTo() whatever the roles say, wrapping Spatie's method through the trait alias the
 * way an app with per-user revocations does, so every check through the Gate honours them.
 */
final class TestUser extends User
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

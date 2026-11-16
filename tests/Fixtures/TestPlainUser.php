<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Contracts\HasPermissionSpace;
use Igne\Permissions\Contracts\SpaceEnum;
use Illuminate\Foundation\Auth\User;

/**
 * A user of an app without Spatie: the test's own Gate::before callback grants the names in
 * $grantedPermissions, the way an app grants from its own storage.
 */
final class TestPlainUser extends User implements HasPermissionSpace
{
    /**
     * @var list<string>
     */
    public array $grantedPermissions = [];

    /**
     * @var SpaceEnum|list<SpaceEnum>
     */
    public SpaceEnum|array $space = [];

    /**
     * @return SpaceEnum|list<SpaceEnum>
     */
    public function permissionSpace(): SpaceEnum|array
    {
        return $this->space;
    }
}

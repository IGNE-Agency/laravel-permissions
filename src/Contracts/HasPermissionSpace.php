<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\Permissions;

/**
 * The space a permission is checked in for this user when the name was built without in().
 *
 * permissions()->for($case)->view() renders the space as the {@see Permissions::SPACE_PLACEHOLDER}
 * placeholder; the package's Gate hook replaces it with this space for the user the Gate is
 * checking, then runs the real check on the concrete name.
 *
 * The method must not build a name without in() itself: checking that name would call this
 * method again.
 */
interface HasPermissionSpace
{
    public function permissionSpace(): SpaceEnum;
}

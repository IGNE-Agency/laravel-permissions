<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\Permissions;

/**
 * The space or spaces a permission is checked in for this user when the name was built without
 * in().
 *
 * permission()->view($subject) renders the space as the {@see Permissions::SPACE_PLACEHOLDER}
 * placeholder; the package's Gate hook replaces it with each of these spaces for the user the Gate
 * is checking and grants when any of the concrete names is granted. An empty list grants nothing.
 *
 * The method must not build a name without in() itself: checking that name would call this
 * method again.
 */
interface HasPermissionSpace
{
    /**
     * @return SpaceEnum|list<SpaceEnum>
     */
    public function permissionSpace(): SpaceEnum|array;
}

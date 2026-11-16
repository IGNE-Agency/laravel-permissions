<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\Permissions;

/**
 * Opt-in capability: a permission subject scoped to spaces, rendered with a "spacePrefix:…" prefix.
 * Implement it on a {@see PermissionSubject} class or a {@see PermissionEnum}.
 *
 * Every space a subject declares automatically gains the built-in "access" permission
 * ({@see Permissions::ACCESS}) for each of its spaces.
 *
 * A subject renders in every space it declares. {@see FiltersPerSpace} narrows that per space, for
 * a whole class or per enum case.
 */
interface UsesSpaces
{
    /**
     * @return list<SpaceEnum>
     */
    public static function spaces(): array;
}

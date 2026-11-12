<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\Permissions;

/**
 * Opt-in capability: a permission enum scoped to spaces, rendered with a "spacePrefix:…" prefix.
 *
 * Every space-using enum automatically gains the built-in "access" permission
 * ({@see Permissions::ACCESS}) for each of its spaces.
 *
 * By default every case is available in every space; implement {@see FiltersCasesPerSpace}
 * to restrict which cases appear per space.
 */
interface UsesSpaces
{
    /**
     * @return list<SpaceEnum>
     */
    public static function spaces(): array;
}

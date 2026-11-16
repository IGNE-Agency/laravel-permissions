<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\Concerns\FiltersCases;

/**
 * Opt-in refinement of {@see UsesSpaces}: whether a subject exists in each of its spaces. A subject
 * that is not allowed in a space has no names there, so nobody can be granted anything on it in
 * that space, and building one of its names with in() for that space throws.
 *
 * A {@see PermissionSubject} class returns a bool. A {@see PermissionEnum} returns the cases
 * allowed in the space, or a bool for all or none of them; the companion {@see FiltersCases} trait
 * gives only()/except()/all()/none() for the match arms.
 */
interface FiltersPerSpace
{
    /**
     * @return bool|list<PermissionEnum>
     */
    public static function allowedForSpace(SpaceEnum $space): bool|array;
}

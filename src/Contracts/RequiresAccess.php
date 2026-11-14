<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\SpaceAccessRule;

/**
 * Opt-in for a space enum: the spaces where every permission needs the space's built-in "access"
 * permission as well. A user granted nova:document:view without nova:access is denied, so a role
 * that loses access to a space loses everything in it at once.
 *
 * The package's Gate hook enforces it ({@see SpaceAccessRule}). Spatie's direct methods such as
 * hasPermissionTo() never reach the Gate and see only what was stored.
 */
interface RequiresAccess
{
    /**
     * @return list<SpaceEnum>
     */
    public static function spacesRequiringAccess(): array;
}

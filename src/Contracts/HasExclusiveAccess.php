<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\AccessGuard;

/**
 * Opt-in for a space enum: the spaces whose built-in "access" permissions may never be held by the
 * same user at once, such as a company portal and a customer portal. {@see AccessGuard} enforces it.
 */
interface HasExclusiveAccess
{
    /**
     * @return list<SpaceEnum>
     */
    public static function exclusiveAccessSpaces(): array;
}

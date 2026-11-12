<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use BackedEnum;

/**
 * A space is an area of the app with its own built-in "access" permission, such as an admin
 * panel or a customer portal. Its prefix is the first segment of every name in that space.
 */
interface SpaceEnum extends BackedEnum
{
    public function spacePrefix(): string;
}

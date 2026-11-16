<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

/**
 * A {@see UsesSpaces} subject that lists its own spaces instead of every space.
 */
interface UsesCustomSpaces extends UsesSpaces
{
    /**
     * @return list<SpaceEnum>
     */
    public static function spaces(): array;
}

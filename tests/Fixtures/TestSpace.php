<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Contracts\HasExclusiveAccess;
use Igne\Permissions\Contracts\SpaceEnum;

/**
 * A space whose prefix deliberately differs from its value (ADMIN renders as 'backend') to prove
 * the rendered name uses spacePrefix(), not the case value. PORTAL and GUEST access exclude each other.
 */
enum TestSpace: string implements HasExclusiveAccess, SpaceEnum
{
    case ADMIN = 'admin';
    case PORTAL = 'portal';
    case GUEST = 'guest';

    public function spacePrefix(): string
    {
        return match ($this) {
            self::ADMIN => 'backend',
            default => $this->value,
        };
    }

    /**
     * @return list<SpaceEnum>
     */
    public static function exclusiveAccessSpaces(): array
    {
        return [self::PORTAL, self::GUEST];
    }
}

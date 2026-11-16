<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Contracts\RequiresAccess;
use Igne\Permissions\Contracts\SpaceEnum;

/**
 * Two spaces of which only OFFICE requires its access permission next to every other grant.
 */
enum TestGatedSpace: string implements RequiresAccess, SpaceEnum
{
    case OFFICE = 'office';
    case KIOSK = 'kiosk';

    public function spacePrefix(): string
    {
        return $this->value;
    }

    /**
     * @return list<SpaceEnum>
     */
    public static function spacesRequiringAccess(): array
    {
        return [self::OFFICE];
    }
}

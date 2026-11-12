<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Concerns\FiltersCases;
use Igne\Permissions\Contracts\FiltersCasesPerSpace;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesSpaces;

/**
 * A space-using enum without actions: "space:permission". Exercises the all()/only()/except()
 * case-filter helpers across the three spaces.
 */
enum TestGuardPermission: string implements FiltersCasesPerSpace, PermissionEnum, UsesSpaces
{
    use FiltersCases;

    case MANAGE_USERS = 'manageUsers';
    case MANAGE_ACCOUNT = 'manageAccount';
    case RUN_REPORTS = 'runReports';

    /**
     * @return list<SpaceEnum>
     */
    public static function spaces(): array
    {
        return TestSpace::cases();
    }

    /**
     * @return list<PermissionEnum>
     */
    public static function allowedCasesForSpace(SpaceEnum $space): array
    {
        return match ($space) {
            TestSpace::ADMIN => self::all(),
            TestSpace::PORTAL => self::only([self::MANAGE_ACCOUNT]),
            TestSpace::GUEST => self::except([self::MANAGE_USERS, self::MANAGE_ACCOUNT]),
            default => self::none(),
        };
    }
}

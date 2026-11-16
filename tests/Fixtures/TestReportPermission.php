<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Concerns\ResolvesPermissions;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesSpaces;

/**
 * A space-using enum with an app-defined action set and no case filter: every case is allowed in
 * every space.
 */
enum TestReportPermission: string implements PermissionEnum, UsesActions, UsesSpaces
{
    use ResolvesPermissions;

    case REPORT = 'Report';

    /**
     * @return list<PermissionActionEnum>
     */
    public static function actions(): array
    {
        return TestCustomAction::cases();
    }

    /**
     * @return list<SpaceEnum>
     */
    public static function spaces(): array
    {
        return TestSpace::cases();
    }
}

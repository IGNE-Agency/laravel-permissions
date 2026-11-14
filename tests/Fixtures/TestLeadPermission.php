<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Concerns\ResolvesPermissions;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesGroup;
use Igne\Permissions\Contracts\UsesSpaces;
use Igne\Permissions\PermissionAction;

/**
 * A grouped enum: "space:group:permission:action", only in the PORTAL space and with two of the
 * default actions. The group is PascalCase to exercise normalization.
 */
enum TestLeadPermission: string implements PermissionEnum, UsesActions, UsesGroup, UsesSpaces
{
    use ResolvesPermissions;

    case PHONE = 'phone';
    case WEB_FORM = 'WebForm';

    public static function group(): string
    {
        return 'LeadSales';
    }

    /**
     * @return list<PermissionActionEnum>
     */
    public static function actions(): array
    {
        return [PermissionAction::VIEW_ANY, PermissionAction::UPDATE];
    }

    /**
     * @return list<SpaceEnum>
     */
    public static function spaces(): array
    {
        return [TestSpace::PORTAL];
    }
}

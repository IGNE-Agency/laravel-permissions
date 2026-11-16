<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Concerns\ResolvesPermissions;
use Igne\Permissions\Contracts\ImpliesActions;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\PermissionAction;

/**
 * Misconfigured on purpose: write implies an action the enum does not list.
 */
enum TestBrokenImplicationPermission: string implements ImpliesActions, PermissionEnum, UsesActions
{
    use ResolvesPermissions;

    case ATTACHMENT = 'attachment';

    /**
     * @return list<PermissionActionEnum>
     */
    public static function actions(): array
    {
        return [TestCustomAction::READ, TestCustomAction::WRITE];
    }

    /**
     * @return list<PermissionActionEnum>
     */
    public static function impliedActions(PermissionActionEnum $action): array
    {
        return $action === TestCustomAction::WRITE ? [TestCustomAction::READ, PermissionAction::DELETE] : [];
    }
}

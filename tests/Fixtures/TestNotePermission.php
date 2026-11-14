<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Concerns\ResolvesPermissions;
use Igne\Permissions\Concerns\UsesDefaultActions;
use Igne\Permissions\Contracts\ImpliesActions;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\PermissionAction;

/**
 * An enum without spaces whose update grants view: "note:update" implies "note:view".
 */
enum TestNotePermission: string implements ImpliesActions, PermissionEnum, UsesActions
{
    use ResolvesPermissions;
    use UsesDefaultActions;

    case NOTE = 'note';

    /**
     * @return list<PermissionActionEnum>
     */
    public static function impliedActions(PermissionActionEnum $action): array
    {
        return $action === PermissionAction::UPDATE ? [PermissionAction::VIEW] : [];
    }
}

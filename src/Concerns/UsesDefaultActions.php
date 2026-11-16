<?php

declare(strict_types=1);

namespace Igne\Permissions\Concerns;

use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\UsesCustomActions;
use Igne\Permissions\PermissionDefaults;

/**
 * The default actions as actions(), for a {@see UsesCustomActions} subject whose list starts from
 * them: define actions() and build on self::defaultActions().
 */
trait UsesDefaultActions
{
    /**
     * @return list<PermissionActionEnum>
     */
    public static function actions(): array
    {
        return self::defaultActions();
    }

    /**
     * @return list<PermissionActionEnum>
     */
    protected static function defaultActions(): array
    {
        return PermissionDefaults::actions();
    }
}

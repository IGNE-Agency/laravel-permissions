<?php

declare(strict_types=1);

namespace Igne\Permissions\Concerns;

use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\PermissionAction;

/**
 * Provides the package's default action set for a {@see UsesActions} enum. Use this trait to opt
 * into the default; declare your own actions() to use a different set.
 */
trait UsesDefaultActions
{
    /**
     * @return list<PermissionActionEnum>
     */
    public static function actions(): array
    {
        return PermissionAction::cases();
    }
}

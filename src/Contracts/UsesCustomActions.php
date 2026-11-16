<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\Concerns\UsesDefaultActions;

/**
 * A {@see UsesActions} subject that lists its own actions instead of the default ones.
 * {@see UsesDefaultActions} returns the defaults, for a list that starts from them.
 */
interface UsesCustomActions extends UsesActions
{
    /**
     * @return list<PermissionActionEnum>
     */
    public static function actions(): array;
}

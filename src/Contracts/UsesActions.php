<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\Concerns\UsesDefaultActions;
use Igne\Permissions\PermissionAction;

/**
 * Opt-in capability: a permission subject or enum with an action axis, rendered as "…:permission:action".
 *
 * {@see UsesDefaultActions} provides the package's {@see PermissionAction} set, so implementers
 * only write actions() themselves to use a different set.
 */
interface UsesActions
{
    /**
     * @return list<PermissionActionEnum>
     */
    public static function actions(): array;
}

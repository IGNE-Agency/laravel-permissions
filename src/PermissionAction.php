<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\HasLabel;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\UsesActions;

/**
 * The default action set for every {@see UsesActions} enum that does not define its own actions().
 *
 * The values are stored data in every installing app; they never change.
 */
enum PermissionAction: string implements HasLabel, PermissionActionEnum
{
    case VIEW_ANY = 'viewAny';
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case RESTORE = 'restore';
    case DESTROY = 'destroy';

    public function label(): string
    {
        return match ($this) {
            self::VIEW_ANY => __('View Any'),
            self::VIEW => __('View'),
            self::CREATE => __('Create'),
            self::UPDATE => __('Update'),
            self::DELETE => __('Delete'),
            self::RESTORE => __('Restore'),
            self::DESTROY => __('Destroy'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::VIEW_ANY => __('View a list of all items for this resource.'),
            self::VIEW => __('View any single item of this resource.'),
            self::CREATE => __('Create a new item for this resource.'),
            self::UPDATE => __('Update any single item of this resource.'),
            self::DELETE => __('Delete any single item of this resource.'),
            self::RESTORE => __('Restore any deleted item of this resource.'),
            self::DESTROY => __('Permanently delete any item of this resource.'),
        };
    }
}

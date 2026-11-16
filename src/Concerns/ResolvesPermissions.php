<?php

declare(strict_types=1);

namespace Igne\Permissions\Concerns;

use Igne\Permissions\Contracts\PermissionEnum;

/**
 * Default permissions() for a permission enum: every case.
 *
 * This is the only always-safe default, so it is the only thing this trait provides. Action and
 * case-filter defaults live in their own capability traits ({@see UsesDefaultActions},
 * {@see FiltersCases}) so an enum never gains a helper for a capability it did not opt into.
 */
trait ResolvesPermissions
{
    /**
     * @return list<PermissionEnum>
     */
    public static function permissions(): array
    {
        return static::cases();
    }
}

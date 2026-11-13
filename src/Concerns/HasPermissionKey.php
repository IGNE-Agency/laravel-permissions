<?php

declare(strict_types=1);

namespace Igne\Permissions\Concerns;

use Igne\Permissions\Contracts\PermissionSubject;

/**
 * The default {@see PermissionSubject::permissionKey()}: the class name without its namespace, so
 * App\Models\Organization renders as "organization" with the default normalizer.
 *
 * Renaming the class renames every stored name. Define permissionKey() on the class to keep the
 * old key.
 */
trait HasPermissionKey
{
    public static function permissionKey(): string
    {
        return class_basename(static::class);
    }
}

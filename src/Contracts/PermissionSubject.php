<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\Concerns\HasPermissionKey;

/**
 * A class the package builds permission names for, such as a model or an action class. The package
 * discovers every class that implements it; see the permissions.discover config.
 *
 * {@see HasPermissionKey} derives the key from the class name. Define permissionKey() yourself to
 * keep a stored name when the class is renamed.
 *
 * Opt in to a space prefix, an action suffix or a group segment by also implementing
 * {@see UsesSpaces}, {@see UsesActions} or {@see UsesGroup}.
 */
interface PermissionSubject
{
    /**
     * The permission segment of every name for this class, as in "post" of
     * "admin:post:view". The configured normalizer cases it like every other segment.
     */
    public static function permissionKey(): string;
}

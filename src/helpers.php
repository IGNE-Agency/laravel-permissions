<?php

declare(strict_types=1);

use Igne\Permissions\PermissionRegistry;
use Igne\Permissions\Permissions;

if (! function_exists('permissions')) {
    /**
     * The permission name chain, built from the current config.
     */
    function permissions(): Permissions
    {
        return new Permissions(PermissionRegistry::fromConfig());
    }
}

<?php

declare(strict_types=1);

use Igne\Permissions\Permission;
use Igne\Permissions\PermissionRegistry;
use Igne\Permissions\Permissions;

if (! function_exists('permission')) {
    /**
     * One permission name, built from the current config: permission()->view(Post::class).
     */
    function permission(): Permission
    {
        return new Permission(PermissionRegistry::fromConfig());
    }
}

if (! function_exists('permissions')) {
    /**
     * Lists of permission names, built from the current config: permissions()->all().
     */
    function permissions(): Permissions
    {
        return new Permissions(PermissionRegistry::fromConfig());
    }
}

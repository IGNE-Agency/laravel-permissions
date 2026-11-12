<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

/**
 * Opt-in capability: a permission enum nested under a shared group, rendered as
 * "…:group:permission…" between the space prefix and the permission.
 */
interface UsesGroup
{
    public static function group(): string;
}

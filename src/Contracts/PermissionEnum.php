<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use BackedEnum;
use Igne\Permissions\Concerns\ResolvesPermissions;

/**
 * A permission enum the package builds names for. Every case is a permission; permissions()
 * lists the cases that take part. {@see ResolvesPermissions} defaults it to every case.
 *
 * Opt in to a space prefix, an action suffix, a group segment or per-space case filtering by
 * also implementing {@see UsesSpaces}, {@see UsesActions}, {@see UsesGroup} or
 * {@see FiltersCasesPerSpace}. Labels shown to users are opt-in through {@see HasLabel}.
 */
interface PermissionEnum extends BackedEnum
{
    /**
     * Every permission case of this enum.
     *
     * @return list<PermissionEnum>
     */
    public static function permissions(): array;
}

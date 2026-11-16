<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use BackedEnum;
use Igne\Permissions\Concerns\ResolvesPermissions;

/**
 * A permission enum: every case is a permission subject of its own, its value the key. Use it for
 * permissions with no class behind them, such as "setupTwoFactor". The package discovers it like a
 * {@see PermissionSubject} class. permissions() lists the cases that take part;
 * {@see ResolvesPermissions} defaults it to every case.
 *
 * Opt in to a space prefix, an action suffix, a group segment or per-space case filtering by
 * also implementing {@see UsesSpaces}, {@see UsesActions}, {@see UsesGroup} or
 * {@see FiltersPerSpace}. Labels shown to users are opt-in through {@see HasLabel}.
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

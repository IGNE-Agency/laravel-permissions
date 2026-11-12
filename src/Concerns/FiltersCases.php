<?php

declare(strict_types=1);

namespace Igne\Permissions\Concerns;

use Igne\Permissions\Contracts\FiltersCasesPerSpace;
use Igne\Permissions\Contracts\PermissionEnum;

/**
 * Helpers for the match arms of {@see FiltersCasesPerSpace::allowedCasesForSpace()}, as in
 * `Space::ADMIN => self::only([self::MANAGE_ACCOUNT])` or `Space::GUEST => self::none()`.
 *
 * Composes {@see ResolvesPermissions} so a filtering enum gets its permissions() default too.
 */
trait FiltersCases
{
    use ResolvesPermissions;

    /**
     * @param  list<PermissionEnum>  $cases
     * @return list<PermissionEnum>
     */
    protected static function only(array $cases): array
    {
        return $cases;
    }

    /**
     * @param  list<PermissionEnum>  $excluded
     * @return list<PermissionEnum>
     */
    protected static function except(array $excluded): array
    {
        return array_values(array_filter(
            static::permissions(),
            static fn (PermissionEnum $case): bool => ! in_array($case, $excluded, true),
        ));
    }

    /**
     * @return list<PermissionEnum>
     */
    protected static function all(): array
    {
        return static::permissions();
    }

    /**
     * @return list<PermissionEnum>
     */
    protected static function none(): array
    {
        return [];
    }
}

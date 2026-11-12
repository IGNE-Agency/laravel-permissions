<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

use Igne\Permissions\Concerns\FiltersCases;

/**
 * Opt-in refinement of {@see UsesSpaces}: restrict which permission cases appear in each space.
 *
 * Without this contract every case is allowed in every space. The companion {@see FiltersCases}
 * trait provides only()/except()/all()/none() helpers for the match arms, as in
 * `Space::ADMIN => self::only([self::MANAGE_ACCOUNT])`.
 */
interface FiltersCasesPerSpace
{
    /**
     * The permission cases available within the given space.
     *
     * @return list<PermissionEnum>
     */
    public static function allowedCasesForSpace(SpaceEnum $space): array;
}

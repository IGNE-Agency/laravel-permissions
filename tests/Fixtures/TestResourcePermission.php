<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Concerns\FiltersCases;
use Igne\Permissions\Concerns\UsesDefaultActions;
use Igne\Permissions\Contracts\FiltersPerSpace;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesSpaces;

/**
 * A space-using enum with the package's default action set: "space:permission:action".
 * Available only in the ADMIN space. Values are PascalCase to exercise normalization.
 */
enum TestResourcePermission: string implements FiltersPerSpace, PermissionEnum, UsesActions, UsesSpaces
{
    use FiltersCases;
    use UsesDefaultActions;

    case ARTICLE = 'Article';
    case PROFILE = 'Profile';
    case USER = 'User';
    case BLOG_POST = 'BlogPost';

    /**
     * @return list<SpaceEnum>
     */
    public static function spaces(): array
    {
        return TestSpace::cases();
    }

    /**
     * @return list<PermissionEnum>
     */
    public static function allowedForSpace(SpaceEnum $space): array
    {
        return $space === TestSpace::ADMIN ? self::all() : self::none();
    }
}

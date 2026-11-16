<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Concerns\HasPermissionKey;
use Igne\Permissions\Contracts\FiltersPerSpace;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesSpaces;
use Illuminate\Database\Eloquent\Model;

/**
 * A model that declares every space but is only allowed outside GUEST, so it has no names there.
 */
final class TestComment extends Model implements FiltersPerSpace, PermissionSubject, UsesActions, UsesSpaces
{
    use HasPermissionKey;

    public static function allowedForSpace(SpaceEnum $space): bool
    {
        return $space !== TestSpace::GUEST;
    }
}

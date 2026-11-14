<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Concerns\HasPermissionKey;
use Igne\Permissions\Concerns\UsesDefaultActions;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesSpaces;
use Illuminate\Database\Eloquent\Model;

/**
 * A model as a permission subject: its key comes from the class name ("testPost"), it has
 * the default actions and lives in the ADMIN and PORTAL spaces.
 */
final class TestPost extends Model implements PermissionSubject, UsesActions, UsesSpaces
{
    use HasPermissionKey;
    use UsesDefaultActions;

    /**
     * @return list<SpaceEnum>
     */
    public static function spaces(): array
    {
        return [TestSpace::ADMIN, TestSpace::PORTAL];
    }
}

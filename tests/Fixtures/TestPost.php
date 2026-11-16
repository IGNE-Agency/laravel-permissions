<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Concerns\HasPermissionKey;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesCustomSpaces;
use Illuminate\Database\Eloquent\Model;

/**
 * A model as a permission subject: its key comes from the class name ("testPost"), it has
 * the default actions and lives in the ADMIN and PORTAL spaces.
 */
final class TestPost extends Model implements PermissionSubject, UsesActions, UsesCustomSpaces
{
    use HasPermissionKey;

    /**
     * @return list<SpaceEnum>
     */
    public static function spaces(): array
    {
        return [TestSpace::ADMIN, TestSpace::PORTAL];
    }
}

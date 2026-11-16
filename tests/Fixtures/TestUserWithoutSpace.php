<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;
use Spatie\Permission\Traits\HasRoles;

/**
 * A Spatie-enabled user that does not implement HasPermissionSpace: a name built without in() is
 * checked in the spaces whose access it holds. No auth guard names this model, so Spatie is told
 * its guard.
 */
final class TestUserWithoutSpace extends User
{
    use HasRoles;

    protected $table = 'users';

    protected string $guard_name = 'web';

    protected $guarded = [];
}

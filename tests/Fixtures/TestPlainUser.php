<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

/**
 * A user of an app without Spatie: the test's own Gate::before callback grants the names in
 * $grantedPermissions, the way an app grants from its own storage.
 */
final class TestPlainUser extends User
{
    /**
     * @var list<string>
     */
    public array $grantedPermissions = [];
}

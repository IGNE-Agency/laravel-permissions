<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;
use Spatie\Permission\Traits\HasRoles;

/**
 * A Spatie-enabled user. Names listed in $revokedPermissions are denied by can() whatever the
 * roles say, standing in for an app's per-user revocations.
 */
final class TestUser extends User
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    /**
     * @var list<string>
     */
    public array $revokedPermissions = [];

    /**
     * @param  iterable<string>|\BackedEnum|string  $abilities
     * @param  mixed  $arguments
     */
    public function can($abilities, $arguments = []): bool
    {
        if (is_string($abilities) && in_array($abilities, $this->revokedPermissions, true)) {
            return false;
        }

        return parent::can($abilities, $arguments);
    }
}

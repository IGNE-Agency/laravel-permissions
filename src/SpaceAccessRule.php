<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\RequiresAccess;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * The Gate hook behind {@see RequiresAccess}: a name in a space that requires access is denied
 * unless the checked user also holds that space's access permission. With access the hook steps
 * aside and the Gate decides as usual, Spatie's hook included.
 *
 * Registered as the first Gate::before callback, ahead of Spatie's, because a before callback can
 * only deny by answering before another one grants.
 */
final class SpaceAccessRule
{
    public function __construct(private readonly Gate $gate) {}

    /**
     * @param  array<mixed>  $arguments
     */
    public function resolve(Authorizable $user, string $ability, array $arguments = []): ?bool
    {
        $registry = PermissionRegistry::fromConfig();
        $space = $registry->spaceRequiringAccess($ability);

        if ($space === null) {
            return null;
        }

        return $this->gate->forUser($user)->allows($registry->accessName($space)) ? null : false;
    }
}

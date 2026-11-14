<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\ImpliesActions;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * The Gate hook behind {@see ImpliesActions}: a name is granted when the checked user holds any
 * name that implies it, checked through the Gate so revocations and the space access rule apply
 * to those names too. Otherwise the hook steps aside and the Gate decides as usual.
 *
 * The implying names already include every indirect one, so the inner checks skip this hook;
 * that also ends cycles such as delete implying restore and restore implying delete.
 */
final class ImpliedActionResolver
{
    private bool $resolving = false;

    public function __construct(private readonly Gate $gate) {}

    /**
     * @param  array<mixed>  $arguments
     */
    public function resolve(Authorizable $user, string $ability, array $arguments = []): ?bool
    {
        if ($this->resolving) {
            return null;
        }

        $implying = PermissionRegistry::fromConfig()->namesImplying($ability);

        if ($implying === []) {
            return null;
        }

        $this->resolving = true;

        try {
            return $this->gate->forUser($user)->any($implying, $arguments) ? true : null;
        } finally {
            $this->resolving = false;
        }
    }
}

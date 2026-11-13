<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\HasPermissionSpace;
use Igne\Permissions\Exceptions\PermissionException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * The Gate hook behind names built without in(): permission()->view($subject).
 *
 * Registered as a Gate::before callback. For an ability that carries
 * {@see Permissions::SPACE_PLACEHOLDER} it takes the space from the user the Gate is checking,
 * renders the concrete name and re-checks that through the Gate for the same user, so the
 * decision is right wherever the Gate is entered: can(), canAny(), Gate::forUser(), @can,
 * authorize(), the can: middleware and Spatie's permission: middleware, in HTTP requests, queued
 * jobs and commands alike. The inner check sees a concrete name, so every other before and
 * after callback, Spatie's included, applies to it. Any other ability is left to the Gate.
 *
 * The user parameter is not nullable on purpose: the Gate skips the hook for guests, who are
 * then denied like for any unknown ability.
 */
final class PermissionSpaceResolver
{
    public function __construct(private readonly Gate $gate) {}

    /**
     * @param  array<mixed>  $arguments
     */
    public function resolve(Authorizable $user, string $ability, array $arguments = []): ?bool
    {
        $registry = PermissionRegistry::fromConfig();

        if (! $registry->hasSpacePlaceholder($ability)) {
            return null;
        }

        if (! $user instanceof HasPermissionSpace) {
            throw PermissionException::missingPermissionSpace($user);
        }

        return $this->gate->forUser($user)->allows(
            $registry->resolveSpacePlaceholder($ability, $user->permissionSpace()),
            $arguments,
        );
    }
}

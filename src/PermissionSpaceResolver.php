<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\HasPermissionSpace;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Exceptions\PermissionException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * The Gate hook behind names built without in(): permission()->view($subject).
 *
 * Registered as a Gate::before callback. For an ability that carries
 * {@see Permissions::SPACE_PLACEHOLDER} it takes the space or spaces from the user the Gate is
 * checking, renders a concrete name per space and grants when the Gate grants any of them for
 * the same user, so the
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

        $spaces = $user->permissionSpace();

        return $this->gate->forUser($user)->any(
            array_map(
                static fn (SpaceEnum $space): string => $registry->resolveSpacePlaceholder($ability, $space),
                $spaces instanceof SpaceEnum ? [$spaces] : $spaces,
            ),
            $arguments,
        );
    }
}

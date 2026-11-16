<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\HasPermissionSpace;
use Igne\Permissions\Contracts\SpaceEnum;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * The Gate hook behind names built without in(): permission()->view($subject).
 *
 * Registered as a Gate::before callback. For an ability that carries
 * {@see Permissions::SPACE_PLACEHOLDER} it takes the space or spaces of the user the Gate is
 * checking: the user's {@see HasPermissionSpace::permissionSpace()}, or else every space whose
 * access permission the user holds. It renders a concrete name per space and grants when the Gate
 * grants any of them for the same user, so the decision is right wherever the Gate is entered: can(), canAny(), Gate::forUser(), @can,
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

        $spaces = $user instanceof HasPermissionSpace ? $user->permissionSpace() : $this->accessibleSpaces($user, $registry);

        return $this->gate->forUser($user)->any(
            array_map(
                static fn (SpaceEnum $space): string => $registry->resolveSpacePlaceholder($ability, $space),
                $spaces instanceof SpaceEnum ? [$spaces] : $spaces,
            ),
            $arguments,
        );
    }

    /**
     * The spaces whose access permission the user holds, for a user that does not name its own.
     * Access names carry no placeholder, so checking them does not come back here.
     *
     * @return list<SpaceEnum>
     */
    private function accessibleSpaces(Authorizable $user, PermissionRegistry $registry): array
    {
        $gate = $this->gate->forUser($user);

        return array_values(array_filter(
            $registry->spaces(),
            static fn (SpaceEnum $space): bool => $gate->allows($registry->accessName($space)),
        ));
    }
}

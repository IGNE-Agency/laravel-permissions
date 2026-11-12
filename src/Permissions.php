<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesSpaces;

/**
 * Entry point of the chain behind the permissions() helper.
 *
 * Every space, permission and action is an enum case passed as an argument, so the editor
 * completes them and PHPStan checks them. Every terminal returns a string or a list of strings:
 * the names Spatie stores.
 */
final class Permissions
{
    /**
     * The built-in permission every space gains: "spacePrefix:access".
     */
    public const string ACCESS = 'access';

    /**
     * The space segment of a name built without in() for a {@see UsesSpaces} enum. The package's
     * Gate hook replaces it with the checked user's own space; see {@see PermissionSpaceResolver}.
     */
    public const string SPACE_PLACEHOLDER = '{space}';

    public function __construct(private readonly PermissionRegistry $registry) {}

    /**
     * Scope the chain to one space, or to several for whole-space lists.
     *
     * @param  SpaceEnum|array<SpaceEnum>  $space
     */
    public function in(SpaceEnum|array $space): SpaceScope
    {
        return new SpaceScope($this->registry, $space);
    }

    /**
     * Select a permission without naming a space.
     *
     * For an enum that does not use spaces this is the only form. For a {@see UsesSpaces} enum the
     * name carries {@see self::SPACE_PLACEHOLDER} where the space would be, so it is only for
     * checks through Laravel's Gate, which resolves the placeholder from the checked user. Seeding,
     * granting and Spatie's direct model methods need in($space)->for().
     */
    public function for(PermissionEnum $permission): PermissionSelection
    {
        $this->registry->assertRegistered($permission);

        return new PermissionSelection($this->registry, $permission, null);
    }

    /**
     * Every permission name across all registered enums and spaces.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return $this->registry->allNames();
    }
}

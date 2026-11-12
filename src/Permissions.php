<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesSpaces;
use Igne\Permissions\Exceptions\PermissionException;

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
     * Select a permission of an enum that does not use spaces.
     */
    public function for(PermissionEnum $permission): PermissionSelection
    {
        if ($permission instanceof UsesSpaces) {
            throw PermissionException::invalidSelection(sprintf('[%s] uses spaces; call permissions()->in($space)->for() instead', $permission::class));
        }

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

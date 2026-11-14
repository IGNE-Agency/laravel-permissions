<?php

declare(strict_types=1);

namespace Igne\Permissions;

/**
 * Entry point behind the permissions() helper: lists of names, for seeding and role definitions.
 * Single names come from the permission() helper, see {@see Permission}.
 *
 * Every terminal returns a list of strings: the names Spatie stores.
 */
final class Permissions
{
    /**
     * The built-in permission every space gains: "spacePrefix:access".
     */
    public const string ACCESS = 'access';

    public function __construct(private readonly PermissionRegistry $registry) {}

    /**
     * Every permission name: the subjects without spaces, then each space with its access permission.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return $this->registry->allNames();
    }
}

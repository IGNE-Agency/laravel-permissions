<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Contracts\SpaceEnum;

/**
 * Entry point behind the permissions() helper: lists of names, for seeding and role definitions.
 * Single names come from the permission() helper, see {@see Permission}.
 *
 * Every terminal returns a list of strings: the names Spatie stores. Lists never carry the space
 * placeholder.
 */
final class Permissions
{
    /**
     * The built-in permission every space gains: "spacePrefix:access".
     */
    public const string ACCESS = 'access';

    /**
     * The space segment of a single name built without in() for a subject that uses spaces. The
     * package's Gate hook replaces it with the checked user's own space; see
     * {@see PermissionSpaceResolver}.
     */
    public const string SPACE_PLACEHOLDER = '{space}';

    public function __construct(private readonly PermissionRegistry $registry) {}

    /**
     * Every name in the given spaces, to filter further or render with names().
     *
     * @param  SpaceEnum|list<SpaceEnum>  $space
     */
    public function in(SpaceEnum|array $space): PermissionQuery
    {
        return PermissionQuery::for($this->registry, $space instanceof SpaceEnum ? [$space] : $space);
    }

    /**
     * Every name, filtered across the subjects without spaces and every space; see
     * {@see PermissionQuery} for the selection.
     *
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum|PermissionActionEnum|array<mixed>  $selection
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function only(string|PermissionSubject|PermissionEnum|PermissionActionEnum|array $selection, PermissionActionEnum|array|null $actions = null): PermissionQuery
    {
        return PermissionQuery::for($this->registry, null)->only($selection, $actions);
    }

    /**
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum|PermissionActionEnum|array<mixed>  $selection
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function except(string|PermissionSubject|PermissionEnum|PermissionActionEnum|array $selection, PermissionActionEnum|array|null $actions = null): PermissionQuery
    {
        return PermissionQuery::for($this->registry, null)->except($selection, $actions);
    }

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

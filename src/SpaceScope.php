<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesSpaces;
use Igne\Permissions\Exceptions\PermissionException;

/**
 * The chain scoped to one or more spaces.
 *
 * Name-building terminals need exactly one space; whole-space lists accept several.
 */
final class SpaceScope
{
    /**
     * @var list<SpaceEnum>
     */
    private readonly array $spaces;

    /**
     * @param  SpaceEnum|array<SpaceEnum>  $space
     */
    public function __construct(private readonly PermissionRegistry $registry, SpaceEnum|array $space)
    {
        $spaces = $space instanceof SpaceEnum ? [$space] : array_values($space);

        if ($spaces === []) {
            throw PermissionException::invalidSelection('in() needs at least one space');
        }

        $this->spaces = $spaces;
    }

    /**
     * The built-in "space:access" permission of this space.
     */
    public function access(): string
    {
        return $this->registry->accessName($this->singleSpace('access'));
    }

    /**
     * Select a permission of a space-using enum within this space.
     */
    public function for(PermissionEnum $permission): PermissionSelection
    {
        if (! $permission instanceof UsesSpaces) {
            throw PermissionException::invalidSelection(sprintf('[%s] does not use spaces; call permissions()->for() without in()', $permission::class));
        }

        $this->registry->assertRegistered($permission);

        return new PermissionSelection($this->registry, $permission, $this->singleSpace('for'));
    }

    /**
     * Every permission name available in this space, including its access permission.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return $this->registry->allNamesForSpace($this->singleSpace('all'));
    }

    /**
     * Build across every registered enum in these spaces at once, with each space's built-in "access"
     * permission guaranteed in the result. The returned query's only()/except() filter whole spaces,
     * the natural shape of a role: "everything except …" or "only …".
     */
    public function withAccess(): SpacePermissionQuery
    {
        return SpacePermissionQuery::for($this->registry, $this->spaces)->withAccess();
    }

    /**
     * Restrict one enum's names in this space to the given permissions, or to the given actions
     * across all of its permissions. The enum is the one the first item belongs to.
     *
     * @param  PermissionEnum|PermissionActionEnum|list<PermissionEnum>|list<PermissionActionEnum>  $permissionsOrActions
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function only(PermissionEnum|PermissionActionEnum|array $permissionsOrActions, PermissionActionEnum|array|null $actions = null): PermissionQuery
    {
        return $this->query($permissionsOrActions, 'only')->only($permissionsOrActions, $actions);
    }

    /**
     * Remove the given permissions, or the given actions across all of one enum's permissions, from
     * that enum's names in this space. The enum is the one the first item belongs to.
     *
     * @param  PermissionEnum|PermissionActionEnum|list<PermissionEnum>|list<PermissionActionEnum>  $permissionsOrActions
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function except(PermissionEnum|PermissionActionEnum|array $permissionsOrActions, PermissionActionEnum|array|null $actions = null): PermissionQuery
    {
        return $this->query($permissionsOrActions, 'except')->except($permissionsOrActions, $actions);
    }

    /**
     * @param  PermissionEnum|PermissionActionEnum|list<PermissionEnum>|list<PermissionActionEnum>  $selection
     */
    private function query(PermissionEnum|PermissionActionEnum|array $selection, string $operation): PermissionQuery
    {
        return PermissionQuery::for($this->registry, $this->registry->typeForSelection($selection), $this->singleSpace($operation));
    }

    private function singleSpace(string $operation): SpaceEnum
    {
        if (count($this->spaces) !== 1) {
            throw PermissionException::invalidSelection(sprintf('%s() needs exactly one space; call in() with a single space', $operation));
        }

        return $this->spaces[0];
    }
}

<?php

declare(strict_types=1);

namespace Igne\Permissions;

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

    private function singleSpace(string $operation): SpaceEnum
    {
        if (count($this->spaces) !== 1) {
            throw PermissionException::invalidSelection(sprintf('%s() needs exactly one space; call in() with a single space', $operation));
        }

        return $this->spaces[0];
    }
}

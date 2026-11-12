<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Countable;
use Igne\Permissions\Contracts\FiltersCasesPerSpace;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesSpaces;
use Igne\Permissions\Exceptions\PermissionException;
use IteratorAggregate;
use Traversable;

/**
 * Immutable list builder for the names of a single enum, optionally within a space.
 *
 * A space prefix only applies to {@see UsesSpaces} enums and per-space case restriction only to
 * {@see FiltersCasesPerSpace} enums; every other enum offers all of
 * its cases everywhere.
 *
 * Chained only()/except() calls are additive: each call is unioned into the allow or deny set,
 * never treated as an AND filter. names() renders the result.
 *
 * @implements IteratorAggregate<int, string>
 */
final class PermissionQuery implements Countable, IteratorAggregate
{
    /**
     * @param  class-string<PermissionEnum>  $type
     * @param  array<string, true>  $only
     * @param  array<string, true>  $except
     */
    private function __construct(
        private readonly PermissionRegistry $registry,
        private readonly string $type,
        private readonly ?SpaceEnum $space,
        private array $only = [],
        private array $except = [],
        private bool $hasOnly = false,
    ) {}

    /**
     * @param  class-string<PermissionEnum>  $type
     */
    public static function for(PermissionRegistry $registry, string $type, ?SpaceEnum $space): self
    {
        return new self($registry, $type, $space);
    }

    /**
     * Add permissions, or actions across every permission, to the allow-list.
     *
     * @param  PermissionEnum|PermissionActionEnum|list<PermissionEnum>|list<PermissionActionEnum>  $permissionsOrActions
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function only(PermissionEnum|PermissionActionEnum|array $permissionsOrActions, PermissionActionEnum|array|null $actions = null): self
    {
        $clone = clone $this;
        $clone->only = $this->only + array_fill_keys($this->expandSelection($permissionsOrActions, $actions), true);
        $clone->hasOnly = true;

        return $clone;
    }

    /**
     * Add permissions, or actions across every permission, to the deny-list.
     *
     * @param  PermissionEnum|PermissionActionEnum|list<PermissionEnum>|list<PermissionActionEnum>  $permissionsOrActions
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function except(PermissionEnum|PermissionActionEnum|array $permissionsOrActions, PermissionActionEnum|array|null $actions = null): self
    {
        $clone = clone $this;
        $clone->except = $this->except + array_fill_keys($this->expandSelection($permissionsOrActions, $actions), true);

        return $clone;
    }

    /**
     * The permission names the query selects, in the enum's own order.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = $this->baseNames();

        if ($this->hasOnly) {
            $names = array_filter($names, fn (string $name): bool => isset($this->only[$name]));
        }

        if ($this->except !== []) {
            $names = array_filter($names, fn (string $name): bool => ! isset($this->except[$name]));
        }

        return array_values(array_unique($names));
    }

    public function count(): int
    {
        return count($this->names());
    }

    /**
     * @return Traversable<int, string>
     */
    public function getIterator(): Traversable
    {
        yield from $this->names();
    }

    /**
     * Every name this enum produces within the space, before allow and deny filtering.
     *
     * @return list<string>
     */
    private function baseNames(): array
    {
        return $this->registry->expand($this->casesInSpace(), $this->actions(), $this->effectiveSpace());
    }

    /**
     * A selection is either a list of actions, expanded across every case in the space, or a list
     * of this enum's cases, expanded with the given actions or all of the enum's actions.
     *
     * @return list<string>
     */
    private function expandSelection(mixed $permissionsOrActions, mixed $actions): array
    {
        $items = is_array($permissionsOrActions) ? array_values($permissionsOrActions) : [$permissionsOrActions];

        if ($this->isActionList($items)) {
            return $this->registry->expand($this->casesInSpace(), $this->actionList($items), $this->effectiveSpace());
        }

        return $this->registry->expand(
            $this->filterToSpace($this->permissionList($items)),
            $actions === null ? $this->actions() : $this->actionList(is_array($actions) ? array_values($actions) : [$actions]),
            $this->effectiveSpace(),
        );
    }

    /**
     * @param  list<mixed>  $items
     */
    private function isActionList(array $items): bool
    {
        if ($this->actions() === [] || $items === []) {
            return false;
        }

        foreach ($items as $item) {
            if (! $item instanceof PermissionActionEnum) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<PermissionActionEnum>
     */
    private function actionList(array $items): array
    {
        $actions = [];

        foreach ($items as $item) {
            if (! $item instanceof PermissionActionEnum) {
                throw PermissionException::invalidSelection('actions must be action enum cases');
            }

            $actions[] = $item;
        }

        return $actions;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<PermissionEnum>
     */
    private function permissionList(array $items): array
    {
        $cases = [];

        foreach ($items as $item) {
            if (! $item instanceof $this->type) {
                throw PermissionException::invalidSelection(sprintf('expected cases of [%s] or a list of actions', $this->type));
            }

            $cases[] = $item;
        }

        return $cases;
    }

    /**
     * @param  list<PermissionEnum>  $cases
     * @return list<PermissionEnum>
     */
    private function filterToSpace(array $cases): array
    {
        if ($this->effectiveSpace() === null) {
            return $cases;
        }

        $allowed = $this->casesInSpace();

        return array_values(array_filter($cases, static fn (PermissionEnum $case): bool => in_array($case, $allowed, true)));
    }

    /**
     * @return list<PermissionEnum>
     */
    private function casesInSpace(): array
    {
        return $this->registry->casesInSpace($this->type, $this->effectiveSpace());
    }

    /**
     * @return list<PermissionActionEnum>
     */
    private function actions(): array
    {
        return $this->registry->actionsOf($this->type);
    }

    private function effectiveSpace(): ?SpaceEnum
    {
        return is_a($this->type, UsesSpaces::class, true) ? $this->space : null;
    }
}

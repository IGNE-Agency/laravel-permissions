<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Countable;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Exceptions\PermissionException;
use IteratorAggregate;
use Traversable;

/**
 * Immutable list builder for the names of one or more whole spaces: every registered enum at once,
 * plus the built-in {@see Permissions::ACCESS} permission.
 *
 * Where {@see PermissionQuery} filters within a single enum, this query filters across whole
 * spaces, which is what a role definition needs. only()/except() are recorded as an ordered list of
 * operations and replayed per space in names():
 *
 *   - the base set is empty when the first operation is only() ("only these"), otherwise the whole
 *     space ("everything except these");
 *   - each only() unions its names in, each except() subtracts them;
 *   - so except(...)->only(...) reads as subtract-then-add-back, while a lone only(...) still means
 *     "restrict to these".
 *
 * onlyFor()/exceptFor() pin an operation to a single space; only()/except() apply to every space in
 * in(). The result is re-ordered to the canonical per-space listing.
 *
 * A selection is a permission case, or a list of cases and [permission, actions] pairs so several
 * permissions, each with their own actions, fit in one call:
 *
 *   ->only([
 *       GuardPermission::SETUP_TWO_FACTOR,                                           // bare: all its actions
 *       [ResourcePermission::CLIENT_CASE, [PermissionAction::VIEW, PermissionAction::UPDATE]], // pair
 *   ])
 *
 * @implements IteratorAggregate<int, string>
 */
final class SpacePermissionQuery implements Countable, IteratorAggregate
{
    /**
     * @param  list<SpaceEnum>  $spaces
     * @param  list<array{type: 'only'|'except', space: ?SpaceEnum, selection: mixed, actions: mixed}>  $operations
     */
    private function __construct(
        private readonly PermissionRegistry $registry,
        private readonly array $spaces,
        private array $operations = [],
        private bool $withAccess = false,
    ) {}

    /**
     * @param  list<SpaceEnum>  $spaces
     */
    public static function for(PermissionRegistry $registry, array $spaces): self
    {
        return new self($registry, $spaces);
    }

    /**
     * Force each space's "access" permission into the result, even when an only() filter would
     * otherwise exclude it.
     */
    public function withAccess(): self
    {
        $clone = clone $this;
        $clone->withAccess = true;

        return $clone;
    }

    /**
     * Add permissions (optionally scoped to actions) to the allow-list, for every space in in().
     *
     * @param  PermissionEnum|list<PermissionEnum|array{0: PermissionEnum, 1: PermissionActionEnum|list<PermissionActionEnum>}>  $selection
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function only(PermissionEnum|array $selection, PermissionActionEnum|array|null $actions = null): self
    {
        return $this->withOperation('only', null, $selection, $actions);
    }

    /**
     * Remove permissions (optionally scoped to actions) from the result, for every space in in().
     *
     * @param  PermissionEnum|list<PermissionEnum|array{0: PermissionEnum, 1: PermissionActionEnum|list<PermissionActionEnum>}>  $selection
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function except(PermissionEnum|array $selection, PermissionActionEnum|array|null $actions = null): self
    {
        return $this->withOperation('except', null, $selection, $actions);
    }

    /**
     * Like only(), but scoped to a single space; other spaces in in() are unaffected.
     *
     * @param  PermissionEnum|list<PermissionEnum|array{0: PermissionEnum, 1: PermissionActionEnum|list<PermissionActionEnum>}>  $selection
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function onlyFor(SpaceEnum $space, PermissionEnum|array $selection, PermissionActionEnum|array|null $actions = null): self
    {
        return $this->withOperation('only', $space, $selection, $actions);
    }

    /**
     * Like except(), but scoped to a single space; other spaces in in() are unaffected.
     *
     * @param  PermissionEnum|list<PermissionEnum|array{0: PermissionEnum, 1: PermissionActionEnum|list<PermissionActionEnum>}>  $selection
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function exceptFor(SpaceEnum $space, PermissionEnum|array $selection, PermissionActionEnum|array|null $actions = null): self
    {
        return $this->withOperation('except', $space, $selection, $actions);
    }

    /**
     * The permission names the query selects, space by space in canonical order.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];

        foreach ($this->spaces as $space) {
            $names = [...$names, ...$this->namesForSpace($space)];
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
     * Record an operation. The selection's shape is checked here, at the call site; name resolution
     * waits until names().
     *
     * @param  'only'|'except'  $type
     */
    private function withOperation(string $type, ?SpaceEnum $space, mixed $selection, mixed $actions): self
    {
        $this->entries($selection, $actions);

        $clone = clone $this;
        $clone->operations = [...$this->operations, ['type' => $type, 'space' => $space, 'selection' => $selection, 'actions' => $actions]];

        return $clone;
    }

    /**
     * Replay the operations applicable to one space and return its names in canonical order.
     *
     * @return list<string>
     */
    private function namesForSpace(SpaceEnum $space): array
    {
        $applicable = array_values(array_filter(
            $this->operations,
            static fn (array $operation): bool => $operation['space'] === null || $operation['space'] === $space,
        ));

        // The first operation sets the mode:
        //   only-first   → restrict: start empty, only() unions in, except() subtracts (a focused allow-list);
        //   except-first → cap: start from the whole space, except() subtracts, and only() restricts each
        //                  listed permission to exactly its listed actions while leaving the rest included.
        $restrict = isset($applicable[0]) && $applicable[0]['type'] === 'only';

        $working = $restrict ? [] : $this->registry->allNamesForSpace($space);

        foreach ($applicable as $operation) {
            if ($operation['type'] === 'except') {
                $working = array_values(array_diff($working, $this->expandSelection($operation['selection'], $operation['actions'], $space)));

                continue;
            }

            $working = $restrict
                ? [...$working, ...$this->expandSelection($operation['selection'], $operation['actions'], $space)]
                : $this->capInto($working, $operation['selection'], $operation['actions'], $space);
        }

        if ($this->withAccess) {
            $working[] = $this->registry->accessName($space);
        }

        $allowed = array_flip($working);

        return array_values(array_filter(
            $this->registry->allNamesForSpace($space),
            static fn (string $name): bool => isset($allowed[$name]),
        ));
    }

    /**
     * Restrict each permission named in the selection to exactly its listed actions: drop all of that
     * permission's names, then add back only the listed ones. Permissions not named are untouched.
     *
     * @param  list<string>  $working
     * @return list<string>
     */
    private function capInto(array $working, mixed $selection, mixed $actions, SpaceEnum $space): array
    {
        foreach ($this->entries($selection, $actions) as [$permission, $entryActions]) {
            $working = [
                ...array_values(array_diff($working, $this->namesFor($permission, null, $space))),
                ...$this->namesFor($permission, $entryActions, $space),
            ];
        }

        return $working;
    }

    /**
     * Normalise a selection into [permission, actions] pairs (actions null: all of the permission's).
     *
     * @return list<array{0: PermissionEnum, 1: list<PermissionActionEnum>|null}>
     */
    private function entries(mixed $selection, mixed $actions): array
    {
        if (! is_array($selection)) {
            return [[$this->permissionOf($selection), $this->actionList($actions)]];
        }

        if ($actions !== null) {
            throw PermissionException::invalidSelection('pass actions inside each entry, not as a second argument, when the selection is a list');
        }

        $entries = [];

        foreach ($selection as $entry) {
            $entries[] = is_array($entry)
                ? [$this->permissionOf($entry[0] ?? null), $this->actionList($entry[1] ?? null)]
                : [$this->permissionOf($entry), null];
        }

        return $entries;
    }

    /**
     * Resolve a selection into the names it covers within a space.
     *
     * @return list<string>
     */
    private function expandSelection(mixed $selection, mixed $actions, SpaceEnum $space): array
    {
        $names = [];

        foreach ($this->entries($selection, $actions) as [$permission, $entryActions]) {
            $names = [...$names, ...$this->namesFor($permission, $entryActions, $space)];
        }

        return $names;
    }

    /**
     * Render one permission's names within the space, delegating to the single-enum query.
     *
     * @param  list<PermissionActionEnum>|null  $actions
     * @return list<string>
     */
    private function namesFor(PermissionEnum $permission, ?array $actions, SpaceEnum $space): array
    {
        return PermissionQuery::for($this->registry, $permission::class, $space)
            ->only($permission, $actions)
            ->names();
    }

    private function permissionOf(mixed $value): PermissionEnum
    {
        if ($value instanceof PermissionActionEnum) {
            throw PermissionException::invalidSelection('select permissions, not bare actions; pair an action with its permission instead');
        }

        if (! $value instanceof PermissionEnum) {
            throw PermissionException::invalidSelection('expected a permission case or a [permission, actions] pair');
        }

        $this->registry->assertRegistered($value);

        return $value;
    }

    /**
     * @return list<PermissionActionEnum>|null
     */
    private function actionList(mixed $actions): ?array
    {
        if ($actions === null) {
            return null;
        }

        $items = is_array($actions) ? array_values($actions) : [$actions];
        $list = [];

        foreach ($items as $item) {
            if (! $item instanceof PermissionActionEnum) {
                throw PermissionException::invalidSelection('each pair must be [permission, actions] with action enum cases');
            }

            $list[] = $item;
        }

        return $list;
    }
}

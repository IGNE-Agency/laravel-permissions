<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Countable;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Exceptions\PermissionException;
use IteratorAggregate;
use Traversable;

/**
 * Immutable list builder over one or more scopes: the spaces given to in(), or without in() the
 * subjects that use no spaces plus every space. Lists never carry the space placeholder.
 *
 * only() and except() are recorded in order and replayed per scope:
 *
 *   - when the first operation is only(), the list starts empty and each only() adds its names;
 *   - when the first operation is except(), the list starts as the whole scope and each only()
 *     caps every subject it names to exactly the listed actions, leaving other subjects whole;
 *   - each except() removes its names.
 *
 * onlyFor() and exceptFor() pin an operation to one space. withAccess() keeps each space's access
 * permission whatever the operations select. The result follows the scope's own order.
 *
 * A selection is one item or a list of items. An item is a subject (all of its actions), a bare
 * action (that action on every subject that lists it), a [subject, actions] pair, or a
 * subject-class => actions entry. A second $actions argument applies to every subject in the
 * selection:
 *
 *   ->only([Organization::class, PermissionAction::VIEW])            // Organization whole, view on everything
 *   ->only([[Organization::class, [PermissionAction::VIEW]]])        // Organization's view only
 *   ->only([Organization::class => [PermissionAction::VIEW]])         // the same, keyed
 *   ->only([Organization::class, Invoice::class], PermissionAction::VIEW)
 *
 * @phpstan-type Selected array<string, array{subject: Subject, actions: list<PermissionActionEnum>|null}>
 * @phpstan-type Operation array{type: 'only'|'except', space: SpaceEnum|null, subjects: Selected, actions: list<PermissionActionEnum>}
 *
 * @implements IteratorAggregate<int, string>
 */
final class PermissionQuery implements Countable, IteratorAggregate
{
    /**
     * @param  list<SpaceEnum|null>  $scopes
     * @param  list<Operation>  $operations
     */
    private function __construct(
        private readonly PermissionRegistry $registry,
        private readonly array $scopes,
        private array $operations = [],
        private bool $withAccess = false,
    ) {}

    /**
     * @param  list<SpaceEnum>|null  $spaces  null for every scope
     */
    public static function for(PermissionRegistry $registry, ?array $spaces): self
    {
        if ($spaces === []) {
            throw PermissionException::invalidSelection('in() needs at least one space');
        }

        return new self($registry, $spaces ?? $registry->scopes());
    }

    /**
     * Keep each space's access permission in the result, even when only() leaves it out.
     */
    public function withAccess(): self
    {
        $clone = clone $this;
        $clone->withAccess = true;

        return $clone;
    }

    /**
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum|PermissionActionEnum|array<mixed>  $selection
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function only(string|PermissionSubject|PermissionEnum|PermissionActionEnum|array $selection, PermissionActionEnum|array|null $actions = null): self
    {
        return $this->withOperation('only', null, $selection, $actions);
    }

    /**
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum|PermissionActionEnum|array<mixed>  $selection
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function except(string|PermissionSubject|PermissionEnum|PermissionActionEnum|array $selection, PermissionActionEnum|array|null $actions = null): self
    {
        return $this->withOperation('except', null, $selection, $actions);
    }

    /**
     * Like only(), for one space; the other scopes are unaffected.
     *
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum|PermissionActionEnum|array<mixed>  $selection
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function onlyFor(SpaceEnum $space, string|PermissionSubject|PermissionEnum|PermissionActionEnum|array $selection, PermissionActionEnum|array|null $actions = null): self
    {
        return $this->withOperation('only', $space, $selection, $actions);
    }

    /**
     * Like except(), for one space; the other scopes are unaffected.
     *
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum|PermissionActionEnum|array<mixed>  $selection
     * @param  PermissionActionEnum|list<PermissionActionEnum>|null  $actions
     */
    public function exceptFor(SpaceEnum $space, string|PermissionSubject|PermissionEnum|PermissionActionEnum|array $selection, PermissionActionEnum|array|null $actions = null): self
    {
        return $this->withOperation('except', $space, $selection, $actions);
    }

    /**
     * The selected names, scope by scope in the scope's own order.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];

        foreach ($this->scopes as $scope) {
            $names = [...$names, ...$this->namesIn($scope)];
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
     * Record an operation. The selection is resolved and checked here, at the call site.
     *
     * @param  'only'|'except'  $type
     */
    private function withOperation(string $type, ?SpaceEnum $space, mixed $selection, mixed $actions): self
    {
        $clone = clone $this;
        $clone->operations = [...$this->operations, ['type' => $type, 'space' => $space, ...$this->selection($selection, $actions)]];

        return $clone;
    }

    /**
     * @return list<string>
     */
    private function namesIn(?SpaceEnum $scope): array
    {
        $operations = array_values(array_filter(
            $this->operations,
            static fn (array $operation): bool => $operation['space'] === null || $operation['space'] === $scope,
        ));

        $restrict = isset($operations[0]) && $operations[0]['type'] === 'only';
        $working = $restrict ? [] : $this->registry->scopeNames($scope);

        foreach ($operations as $operation) {
            foreach ($this->selectedIn($operation, $scope) as ['subject' => $subject, 'actions' => $actions]) {
                $selected = $this->registry->namesOf($subject, $actions, $scope);

                $working = match (true) {
                    $operation['type'] === 'except' => array_diff($working, $selected),
                    $restrict => [...$working, ...$selected],
                    default => [...array_diff($working, $this->registry->namesOf($subject, null, $scope)), ...$selected],
                };
            }
        }

        if ($this->withAccess && $scope !== null) {
            $working[] = $this->registry->accessName($scope);
        }

        $kept = array_flip($working);

        return array_values(array_filter(
            $this->registry->scopeNames($scope),
            static fn (string $name): bool => isset($kept[$name]),
        ));
    }

    /**
     * The operation's subjects with their actions, the bare actions merged into every subject that
     * lists them.
     *
     * @param  Operation  $operation
     * @return list<array{subject: Subject, actions: list<PermissionActionEnum>|null}>
     */
    private function selectedIn(array $operation, ?SpaceEnum $scope): array
    {
        $selected = $operation['subjects'];

        if ($operation['actions'] !== []) {
            foreach ($this->registry->subjects() as $subject) {
                $listed = array_values(array_filter($operation['actions'], $subject->supports(...)));

                if ($listed !== [] && $subject->rendersIn($scope)) {
                    $selected = self::merged($selected, $subject, $listed);
                }
            }
        }

        return array_values($selected);
    }

    /**
     * Resolve a selection into its subjects with their actions, and its bare actions.
     *
     * @return array{subjects: Selected, actions: list<PermissionActionEnum>}
     */
    private function selection(mixed $selection, mixed $actions): array
    {
        $sharedActions = $actions === null ? null : $this->actionList($actions);
        $items = is_array($selection) ? $selection : [$selection];

        if ($items === []) {
            throw PermissionException::invalidSelection('select at least one subject or action');
        }

        $subjects = [];
        $bareActions = [];

        foreach ($items as $key => $item) {
            if (is_string($key)) {
                $this->assertNoSharedActions($sharedActions);
                $subjects = self::merged($subjects, ...$this->subjectWithActions($key, $this->actionList($item)));

                continue;
            }

            if ($item instanceof PermissionActionEnum) {
                $this->assertNoSharedActions($sharedActions);
                $bareActions[] = $this->listedAction($item);

                continue;
            }

            if (is_array($item)) {
                $this->assertNoSharedActions($sharedActions);
                $subjects = self::merged($subjects, ...$this->subjectWithActions($item[0] ?? null, $this->actionList($item[1] ?? null)));

                continue;
            }

            $subjects = self::merged($subjects, ...$this->subjectWithActions($item, $sharedActions));
        }

        return ['subjects' => $subjects, 'actions' => $bareActions];
    }

    /**
     * @param  list<PermissionActionEnum>|null  $actions
     * @return array{0: Subject, 1: list<PermissionActionEnum>|null}
     */
    private function subjectWithActions(mixed $input, ?array $actions): array
    {
        $subject = $this->registry->subject($input);

        foreach ($actions ?? [] as $action) {
            if (! $subject->supports($action)) {
                throw PermissionException::unsupportedAction($subject, $action);
            }
        }

        return [$subject, $actions];
    }

    /**
     * Add a subject to a selection; its actions join those already selected for it, and null
     * (every action) wins.
     *
     * @param  Selected  $selected
     * @param  list<PermissionActionEnum>|null  $actions
     * @return Selected
     */
    private static function merged(array $selected, Subject $subject, ?array $actions): array
    {
        $existing = array_key_exists($subject->id(), $selected) ? $selected[$subject->id()]['actions'] : [];
        $merged = $actions === null || $existing === null ? null : array_values(array_unique([...$existing, ...$actions], SORT_REGULAR));

        $selected[$subject->id()] = ['subject' => $subject, 'actions' => $merged];

        return $selected;
    }

    private function listedAction(PermissionActionEnum $action): PermissionActionEnum
    {
        foreach ($this->registry->subjects() as $subject) {
            if ($subject->supports($action)) {
                return $action;
            }
        }

        throw PermissionException::invalidSelection(sprintf('no registered subject lists action [%s]', $action->value));
    }

    /**
     * @param  list<PermissionActionEnum>|null  $sharedActions
     */
    private function assertNoSharedActions(?array $sharedActions): void
    {
        if ($sharedActions !== null) {
            throw PermissionException::invalidSelection('a second actions argument applies to subjects only; pair actions with each subject instead');
        }
    }

    /**
     * @return list<PermissionActionEnum>
     */
    private function actionList(mixed $actions): array
    {
        $items = is_array($actions) ? array_values($actions) : [$actions];
        $list = [];

        foreach ($items as $item) {
            if (! $item instanceof PermissionActionEnum) {
                throw PermissionException::invalidSelection('actions must be action enum cases');
            }

            $list[] = $item;
        }

        return $list;
    }
}

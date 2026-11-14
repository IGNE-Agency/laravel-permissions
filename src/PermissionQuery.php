<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Countable;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Exceptions\PermissionException;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * Immutable list builder over one or more scopes: the spaces given to in(), or without in() the
 * subjects that use no spaces plus every space.
 * Lists never carry the space placeholder.
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
 *   ->only([Post::class, PermissionAction::VIEW])            // Post whole, view on everything
 *   ->only([[Post::class, [PermissionAction::VIEW]]])        // Post's view only
 *   ->only([Post::class => [PermissionAction::VIEW]])         // the same, keyed
 *   ->only([Post::class, Comment::class], PermissionAction::VIEW)
 *
 * @phpstan-type Selected array<string, array{subject: Subject, actions: list<PermissionActionEnum>|null}>
 * @phpstan-type Operation array{type: string, space: SpaceEnum|null, subjects: Selected, actions: list<PermissionActionEnum>}
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
        return array_values(collect($this->scopes)
            ->flatMap($this->namesIn(...))
            ->unique()
            ->all());
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
     * Replay the operations that apply to the scope, one selected subject at a time, then keep the
     * scope's own order.
     *
     * @return list<string>
     */
    private function namesIn(?SpaceEnum $scope): array
    {
        $operations = collect($this->operations)
            ->filter(static fn (array $operation): bool => $operation['space'] === null || $operation['space'] === $scope)
            ->values();

        $restrict = ($operations->first()['type'] ?? null) === 'only';

        $kept = $operations
            ->flatMap(fn (array $operation): array => array_map(
                static fn (array $selected): array => ['type' => $operation['type'], ...$selected],
                $this->selectedIn($operation, $scope),
            ))
            ->reduce(
                fn (Collection $names, array $step): Collection => $this->applied($names, $step, $restrict, $scope),
                collect($restrict ? [] : $this->registry->scopeNames($scope)),
            );

        if ($this->withAccess && $scope !== null) {
            $kept->push($this->registry->accessName($scope));
        }

        return array_values(collect($this->registry->scopeNames($scope))->intersect($kept)->all());
    }

    /**
     * One step of the replay: except() removes the subject's selected names; only() adds them when
     * restricting, or caps the subject to them otherwise.
     *
     * @param  Collection<int, string>  $names
     * @param  array{type: string, subject: Subject, actions: list<PermissionActionEnum>|null}  $step
     * @return Collection<int, string>
     */
    private function applied(Collection $names, array $step, bool $restrict, ?SpaceEnum $scope): Collection
    {
        $selected = $this->registry->namesOf($step['subject'], $step['actions'], $scope);

        return match (true) {
            $step['type'] === 'except' => $names->diff($selected),
            $restrict => $names->merge($selected),
            default => $names->diff($this->registry->namesOf($step['subject'], null, $scope))->merge($selected),
        };
    }

    /**
     * The operation's subjects with their actions, the bare actions merged into every subject in
     * the scope that lists them.
     *
     * @param  Operation  $operation
     * @return list<array{subject: Subject, actions: list<PermissionActionEnum>|null}>
     */
    private function selectedIn(array $operation, ?SpaceEnum $scope): array
    {
        $selected = collect($this->registry->subjects())
            ->filter(static fn (Subject $subject): bool => $subject->rendersIn($scope))
            ->map(static fn (Subject $subject): array => [
                'subject' => $subject,
                'actions' => array_values(array_filter($operation['actions'], $subject->supports(...))),
            ])
            ->reject(static fn (array $bare): bool => $bare['actions'] === [])
            ->reduce(static fn (array $selected, array $bare): array => self::merged($selected, $bare['subject'], $bare['actions']), $operation['subjects']);

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

        $entries = collect($items)->map(fn (mixed $item, int|string $key): array => $this->entry($item, $key, $sharedActions));

        return [
            'subjects' => self::subjectsIn($entries->all()),
            'actions' => array_values($entries
                ->flatMap(static fn (array $entry): array => $entry['subject'] === null ? ($entry['actions'] ?? []) : [])
                ->all()),
        ];
    }

    /**
     * The entries' subjects, each with the actions of all its entries merged.
     *
     * @param  array<array{subject: Subject|null, actions: list<PermissionActionEnum>|null}>  $entries
     * @return Selected
     */
    private static function subjectsIn(array $entries): array
    {
        $subjects = [];

        foreach ($entries as $entry) {
            if ($entry['subject'] !== null) {
                $subjects = self::merged($subjects, $entry['subject'], $entry['actions']);
            }
        }

        return $subjects;
    }

    /**
     * One item of a selection: a subject with its actions, or a bare action without a subject.
     *
     * @param  list<PermissionActionEnum>|null  $sharedActions
     * @return array{subject: Subject|null, actions: list<PermissionActionEnum>|null}
     */
    private function entry(mixed $item, int|string $key, ?array $sharedActions): array
    {
        if (is_int($key) && ! $item instanceof PermissionActionEnum && ! is_array($item)) {
            return $this->subjectWithActions($item, $sharedActions);
        }

        $this->assertNoSharedActions($sharedActions);

        return match (true) {
            is_string($key) => $this->subjectWithActions($key, $this->actionList($item)),
            $item instanceof PermissionActionEnum => ['subject' => null, 'actions' => [$this->listedAction($item)]],
            is_array($item) => $this->subjectWithActions($item[0] ?? null, $this->actionList($item[1] ?? null)),
            default => throw PermissionException::invalidSelection('expected a subject, an action, a [subject, actions] pair or a keyed entry'),
        };
    }

    /**
     * @param  list<PermissionActionEnum>|null  $actions
     * @return array{subject: Subject, actions: list<PermissionActionEnum>|null}
     */
    private function subjectWithActions(mixed $input, ?array $actions): array
    {
        $subject = $this->registry->subject($input);
        $unsupported = collect($actions ?? [])->first(static fn (PermissionActionEnum $action): bool => ! $subject->supports($action));

        return $unsupported === null
            ? ['subject' => $subject, 'actions' => $actions]
            : throw PermissionException::unsupportedAction($subject, $unsupported);
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
        return collect($this->registry->subjects())->contains(static fn (Subject $subject): bool => $subject->supports($action))
            ? $action
            : throw PermissionException::invalidSelection(sprintf('no registered subject lists action [%s]', $action->value));
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
        return array_values(collect(is_array($actions) ? $actions : [$actions])
            ->map(static fn (mixed $action): PermissionActionEnum => $action instanceof PermissionActionEnum
                ? $action
                : throw PermissionException::invalidSelection('actions must be action enum cases'))
            ->all());
    }
}

<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\HasExclusiveAccess;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Contracts\RequiresAccess;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\Normalizer\SegmentNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * The registered subjects plus the shared formatting config, and the one place that renders
 * subjects into permission names.
 *
 * Names render per scope: a space, or the scope without a space for subjects that do not use
 * spaces. Every space contributes the built-in {@see Permissions::ACCESS} permission.
 */
final class PermissionRegistry
{
    /**
     * @param  list<Subject>  $subjects
     * @param  array<string, list<PermissionActionEnum>>  $impliedActions  per action value, the actions a grant of it also grants
     */
    public function __construct(
        private readonly array $subjects,
        private readonly string $separator,
        private readonly SegmentNormalizer $normalizer,
        private readonly ?string $prefix,
        private readonly array $impliedActions = [],
        private readonly bool $requiresAccess = false,
    ) {}

    /**
     * Built on every call so apps and tests can change the config at runtime; the folder scan
     * itself is kept per process by {@see SubjectDiscovery}.
     */
    public static function fromConfig(): self
    {
        $prefix = Config::get('permissions.prefix');

        if ($prefix !== null && ! is_string($prefix)) {
            throw PermissionException::invalidConfig('prefix', 'must be a string or null');
        }

        $types = array_values(collect(app(SubjectDiscovery::class)->within(self::stringList('discover')))
            ->merge(self::stringList('subjects'))
            ->unique()
            ->all());

        return new self(
            self::subjectsOf($types),
            Config::string('permissions.separator'),
            SegmentNormalizer::fromName(Config::string('permissions.normalizer')),
            $prefix,
            self::configuredImplications(),
            Config::boolean('permissions.requires_access', false),
        );
    }

    /**
     * @return list<Subject>
     */
    public function subjects(): array
    {
        return $this->subjects;
    }

    /**
     * The registered subject for a subject class, an instance of one, or a permission enum case.
     */
    public function subject(mixed $input): Subject
    {
        $subject = match (true) {
            $input instanceof PermissionEnum => Subject::ofCase($input),
            $input instanceof PermissionSubject => Subject::ofClass($input::class),
            is_string($input) && is_a($input, PermissionSubject::class, true) => Subject::ofClass($input),
            default => throw PermissionException::invalidSelection('expected a permission subject class, an instance of one, or a permission enum case'),
        };

        return collect($this->subjects)->first(static fn (Subject $registered): bool => $registered->is($subject))
            ?? throw PermissionException::unregisteredSubject($subject);
    }

    /**
     * Render one name.
     * A subject that uses spaces renders the {@see Permissions::SPACE_PLACEHOLDER} placeholder in
     * the space position when no space is given.
     */
    public function name(Subject $subject, ?PermissionActionEnum $action, ?SpaceEnum $space): string
    {
        return (string) PermissionName::make(
            permission: $subject->key(),
            action: $action,
            space: $space,
            group: $subject->group(),
            prefix: $this->prefix,
            separator: $this->separator,
            normalizer: $this->normalizer,
            spacePlaceholder: $space === null && $subject->usesSpaces(),
        );
    }

    /**
     * Whether a name was built without a space and still carries the placeholder.
     */
    public function hasSpacePlaceholder(string $name): bool
    {
        return str_contains($name, Permissions::SPACE_PLACEHOLDER);
    }

    /**
     * The concrete name for a space: the placeholder becomes the space prefix, normalized like
     * every other segment, so the result is byte-identical to the name in() renders and seeds.
     */
    public function resolveSpacePlaceholder(string $name, SpaceEnum $space): string
    {
        return str_replace(Permissions::SPACE_PLACEHOLDER, $this->normalizer->__invoke($space->spacePrefix()), $name);
    }

    /**
     * The built-in access permission of a space, as in "admin:access".
     */
    public function accessName(SpaceEnum $space): string
    {
        return (string) PermissionName::make(
            permission: Permissions::ACCESS,
            space: $space,
            prefix: $this->prefix,
            separator: $this->separator,
            normalizer: $this->normalizer,
        );
    }

    /**
     * The names of one subject in a scope: the given actions, or all of its actions when null.
     * A subject without actions renders once. Empty when the subject does not render in the scope.
     *
     * @param  list<PermissionActionEnum>|null  $actions
     * @return list<string>
     */
    public function namesOf(Subject $subject, ?array $actions, ?SpaceEnum $scope): array
    {
        if (! $subject->rendersIn($scope)) {
            return [];
        }

        if ($subject->actions() === []) {
            return [$this->name($subject, null, $scope)];
        }

        return array_map(
            fn (PermissionActionEnum $action): string => $this->name($subject, $action, $scope),
            $actions ?? $subject->actions(),
        );
    }

    /**
     * Every name in a scope: each subject's names, plus the space's access permission.
     *
     * @return list<string>
     */
    public function scopeNames(?SpaceEnum $scope): array
    {
        $names = collect($this->subjects)->flatMap(fn (Subject $subject): array => $this->namesOf($subject, null, $scope));

        if ($scope !== null) {
            $names->push($this->accessName($scope));
        }

        return array_values($names->unique()->all());
    }

    /**
     * Every scope names render in: the one without a space, then every space.
     *
     * @return list<SpaceEnum|null>
     */
    public function scopes(): array
    {
        return [null, ...$this->spaces()];
    }

    /**
     * Every permission name: the subjects without spaces, then each space with its access permission.
     *
     * @return list<string>
     */
    public function allNames(): array
    {
        return array_values(collect($this->scopes())
            ->flatMap(fn (?SpaceEnum $scope): array => $this->scopeNames($scope))
            ->unique()
            ->all());
    }

    /**
     * The distinct spaces the subjects declare, in registration order.
     *
     * @return list<SpaceEnum>
     */
    public function spaces(): array
    {
        return self::distinctSpaces(collect($this->subjects)->flatMap(static fn (Subject $subject): array => $subject->declaredSpaces()));
    }

    /**
     * The spaces whose access permissions exclude each other, collected from the space enums that
     * implement {@see HasExclusiveAccess}.
     *
     * @return list<SpaceEnum>
     */
    public function exclusiveSpaces(): array
    {
        return self::distinctSpaces(collect($this->spaces())
            ->filter(static fn (SpaceEnum $space): bool => $space instanceof HasExclusiveAccess)
            ->flatMap(static fn (HasExclusiveAccess $space): array => $space::exclusiveAccessSpaces()));
    }

    /**
     * Whether every other name in the space needs the space's access permission first. A space
     * enum that implements {@see RequiresAccess} decides for its own spaces; every other space
     * follows the requires_access config.
     */
    public function requiresAccess(SpaceEnum $space): bool
    {
        if ($space instanceof RequiresAccess) {
            return in_array($space, $space::spacesRequiringAccess(), true);
        }

        return $this->requiresAccess;
    }

    /**
     * The space whose built-in access permission a name needs before any grant of it counts: a space
     * that requires access and renders the name. The access name itself needs nothing; a name in
     * no such space returns null.
     */
    public function spaceRequiringAccess(string $name): ?SpaceEnum
    {
        return collect($this->spaces())->first(fn (SpaceEnum $space): bool => $this->requiresAccess($space)
            && $name !== $this->accessName($space)
            && in_array($name, $this->scopeNames($space), true));
    }

    /**
     * The names whose grant also grants the given name: the same subject in the same scope with an
     * action that implies this one, directly or through other actions. Empty for every other name.
     *
     * @return list<string>
     */
    public function namesImplying(string $name): array
    {
        $match = collect($this->subjects)
            ->lazy()
            ->flatMap($this->namedActions(...))
            ->first(fn (array $candidate): bool => $this->name($candidate['subject'], $candidate['action'], $candidate['scope']) === $name);

        if ($match === null) {
            return [];
        }

        return array_map(
            fn (PermissionActionEnum $implying): string => $this->name($match['subject'], $implying, $match['scope']),
            $this->actionsImplying($match['subject'], $match['action']),
        );
    }

    /**
     * Every action of the subject in every scope it renders in.
     *
     * @return list<array{subject: Subject, scope: SpaceEnum|null, action: PermissionActionEnum}>
     */
    private function namedActions(Subject $subject): array
    {
        return array_values(collect($subject->usesSpaces() ? $subject->spaces() : [null])
            ->flatMap(static fn (?SpaceEnum $scope): array => array_map(
                static fn (PermissionActionEnum $action): array => ['subject' => $subject, 'scope' => $scope, 'action' => $action],
                $subject->actions(),
            ))
            ->all());
    }

    /**
     * Every action of the subject whose grant reaches the target through its implications, the
     * target itself excluded, so cycles end.
     *
     * @return list<PermissionActionEnum>
     */
    private function actionsImplying(Subject $subject, PermissionActionEnum $target): array
    {
        $impliedBy = $this->implicationGraph($subject);
        $found = collect();
        $pending = collect([$target]);

        while (($current = $pending->shift()) !== null) {
            $next = collect($impliedBy[self::actionKey($current)] ?? [])
                ->reject(fn (PermissionActionEnum $implying): bool => $implying === $target || $found->has(self::actionKey($implying)))
                ->keyBy(static fn (PermissionActionEnum $implying): string => self::actionKey($implying));

            $found = $found->merge($next);
            $pending = $pending->merge($next->values());
        }

        return array_values(collect($subject->actions())
            ->filter(fn (PermissionActionEnum $action): bool => $found->has(self::actionKey($action)))
            ->all());
    }

    /**
     * For each action of the subject, the actions whose grant implies it: from the subject's own
     * {@see ImpliesActions}, which must name its own actions, or else from the configured
     * implications, limited to the actions the subject lists.
     *
     * @return array<string, list<PermissionActionEnum>>
     */
    private function implicationGraph(Subject $subject): array
    {
        return collect($subject->actions())
            ->flatMap(fn (PermissionActionEnum $action): array => array_map(
                static fn (PermissionActionEnum $implied): array => ['implied' => $implied, 'implying' => $action],
                $this->impliedBy($subject, $action),
            ))
            ->mapToGroups(static fn (array $edge): array => [self::actionKey($edge['implied']) => $edge['implying']])
            ->map(static fn (Collection $implying): array => array_values($implying->all()))
            ->all();
    }

    /**
     * @return list<PermissionActionEnum>
     */
    private function impliedBy(Subject $subject, PermissionActionEnum $action): array
    {
        $declared = $subject->declaredImplications($action);

        if ($declared === null) {
            return array_values(array_filter($this->impliedActions[(string) $action->value] ?? [], $subject->supports(...)));
        }

        $unsupported = collect($declared)->first(static fn (PermissionActionEnum $implied): bool => ! $subject->supports($implied));

        return $unsupported === null ? $declared : throw PermissionException::unsupportedImpliedAction($subject, $action, $unsupported);
    }

    private static function actionKey(PermissionActionEnum $action): string
    {
        return $action::class.'::'.$action->name;
    }

    /**
     * @return array<string, list<PermissionActionEnum>>
     */
    private static function configuredImplications(): array
    {
        $implications = Config::get('permissions.implied_actions', []);

        if (! is_array($implications)) {
            throw PermissionException::invalidConfig('implied_actions', 'must be an array of action values to lists of action cases');
        }

        return collect($implications)
            ->mapWithKeys(static fn (mixed $implied, int|string $action): array => [(string) $action => self::actionCases($implied, (string) $action)])
            ->all();
    }

    /**
     * @return list<PermissionActionEnum>
     */
    private static function actionCases(mixed $implied, string $action): array
    {
        return array_values(collect(is_array($implied) ? $implied : [$implied])
            ->map(static fn (mixed $case): PermissionActionEnum => $case instanceof PermissionActionEnum
                ? $case
                : throw PermissionException::invalidConfig('implied_actions', sprintf('[%s] must list action enum cases', $action)))
            ->all());
    }

    /**
     * @param  Collection<int, SpaceEnum>  $spaces
     * @return list<SpaceEnum>
     */
    private static function distinctSpaces(Collection $spaces): array
    {
        return array_values($spaces
            ->keyBy(static fn (SpaceEnum $space): string => $space::class.'::'.$space->name)
            ->all());
    }

    /**
     * Every subject class becomes one subject; every permission enum one subject per case.
     *
     * @param  list<string>  $types
     * @return list<Subject>
     */
    private static function subjectsOf(array $types): array
    {
        return array_values(collect($types)
            ->flatMap(static fn (string $type): array => match (true) {
                is_a($type, PermissionEnum::class, true) => array_map(Subject::ofCase(...), $type::permissions()),
                is_a($type, PermissionSubject::class, true) => [Subject::ofClass($type)],
                default => throw PermissionException::invalidConfig('subjects', sprintf('[%s] implements neither %s nor %s', $type, PermissionSubject::class, PermissionEnum::class)),
            })
            ->all());
    }

    /**
     * @return list<string>
     */
    private static function stringList(string $key): array
    {
        return array_values(collect(Config::array('permissions.'.$key))
            ->map(static fn (mixed $item): string => is_string($item) ? $item : throw PermissionException::invalidConfig($key, 'every entry must be a string'))
            ->all());
    }
}

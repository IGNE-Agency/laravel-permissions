<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\HasExclusiveAccess;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\PermissionSubject;
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
     */
    public function __construct(
        private readonly array $subjects,
        private readonly string $separator,
        private readonly SegmentNormalizer $normalizer,
        private readonly ?string $prefix,
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
        );
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

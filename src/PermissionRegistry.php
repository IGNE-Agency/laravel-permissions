<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\HasExclusiveAccess;
use Igne\Permissions\Contracts\ImpliesActions;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Contracts\RequiresAccess;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\Normalizer\SegmentNormalizer;
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

        $types = [
            ...app(SubjectDiscovery::class)->within(self::stringList('discover')),
            ...self::stringList('subjects'),
        ];

        return new self(
            self::subjectsOf(array_values(array_unique($types))),
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

        foreach ($this->subjects as $registered) {
            if ($registered->is($subject)) {
                return $registered;
            }
        }

        throw PermissionException::unregisteredSubject($subject);
    }

    /**
     * Render one name. A subject that uses spaces renders the {@see Permissions::SPACE_PLACEHOLDER}
     * placeholder in the space position when no space is given.
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
     * The built-in access permission of a space, as in "nova:access".
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
        $names = [];

        foreach ($this->subjects as $subject) {
            $names = [...$names, ...$this->namesOf($subject, null, $scope)];
        }

        if ($scope !== null) {
            $names[] = $this->accessName($scope);
        }

        return array_values(array_unique($names));
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
        $names = [];

        foreach ($this->scopes() as $scope) {
            $names = [...$names, ...$this->scopeNames($scope)];
        }

        return array_values(array_unique($names));
    }

    /**
     * The distinct spaces the subjects declare, in registration order.
     *
     * @return list<SpaceEnum>
     */
    public function spaces(): array
    {
        $spaces = [];

        foreach ($this->subjects as $subject) {
            foreach ($subject->declaredSpaces() as $space) {
                $spaces[$space::class.'::'.$space->name] = $space;
            }
        }

        return array_values($spaces);
    }

    /**
     * The spaces whose access permissions exclude each other, collected from the space enums that
     * implement {@see HasExclusiveAccess}.
     *
     * @return list<SpaceEnum>
     */
    public function exclusiveSpaces(): array
    {
        $spaces = [];

        foreach ($this->spaces() as $space) {
            if (! $space instanceof HasExclusiveAccess) {
                continue;
            }

            foreach ($space::exclusiveAccessSpaces() as $exclusive) {
                $spaces[$exclusive::class.'::'.$exclusive->name] = $exclusive;
            }
        }

        return array_values($spaces);
    }

    /**
     * The space whose built-in access permission a name needs before any grant of it counts: a space
     * a {@see RequiresAccess} space enum lists that renders the name. The access name itself needs
     * nothing; a name in no such space returns null.
     */
    public function spaceRequiringAccess(string $name): ?SpaceEnum
    {
        foreach ($this->spaces() as $space) {
            if (! $space instanceof RequiresAccess || ! in_array($space, $space::spacesRequiringAccess(), true)) {
                continue;
            }

            if ($name !== $this->accessName($space) && in_array($name, $this->scopeNames($space), true)) {
                return $space;
            }
        }

        return null;
    }

    /**
     * The names whose grant also grants the given name under an {@see ImpliesActions} subject's
     * rules: the same subject in the same scope with an action that implies this one, directly or
     * through other actions. Empty for every other name.
     *
     * @return list<string>
     */
    public function namesImplying(string $name): array
    {
        foreach ($this->subjects as $subject) {
            if (! $subject->impliesActions()) {
                continue;
            }

            $scopes = $subject->usesSpaces() ? $subject->spaces() : [null];

            foreach ($scopes as $scope) {
                foreach ($subject->actions() as $action) {
                    if ($this->name($subject, $action, $scope) !== $name) {
                        continue;
                    }

                    return array_map(
                        fn (PermissionActionEnum $implying): string => $this->name($subject, $implying, $scope),
                        $this->actionsImplying($subject, $action),
                    );
                }
            }
        }

        return [];
    }

    /**
     * Every action of the subject whose grant reaches the target through its implications, the
     * target itself excluded, so cycles end.
     *
     * @return list<PermissionActionEnum>
     */
    private function actionsImplying(Subject $subject, PermissionActionEnum $target): array
    {
        $actions = $subject->actions();
        $impliedBy = [];

        foreach ($actions as $action) {
            foreach ($subject->impliedActions($action) as $implied) {
                if (! in_array($implied, $actions, true)) {
                    throw PermissionException::unsupportedImpliedAction($subject, $action, $implied);
                }

                $impliedBy[self::actionKey($implied)][] = $action;
            }
        }

        $found = [];
        $pending = [$target];

        while ($pending !== []) {
            $current = array_shift($pending);

            foreach ($impliedBy[self::actionKey($current)] ?? [] as $implying) {
                $key = self::actionKey($implying);

                if ($implying !== $target && ! isset($found[$key])) {
                    $found[$key] = $implying;
                    $pending[] = $implying;
                }
            }
        }

        return array_values(array_filter($actions, static fn (PermissionActionEnum $action): bool => isset($found[self::actionKey($action)])));
    }

    private static function actionKey(PermissionActionEnum $action): string
    {
        return $action::class.'::'.$action->name;
    }

    /**
     * Every subject class becomes one subject; every permission enum one subject per case.
     *
     * @param  list<string>  $types
     * @return list<Subject>
     */
    private static function subjectsOf(array $types): array
    {
        $subjects = [];

        foreach ($types as $type) {
            if (is_a($type, PermissionEnum::class, true)) {
                foreach ($type::permissions() as $case) {
                    $subjects[] = Subject::ofCase($case);
                }

                continue;
            }

            if (! is_a($type, PermissionSubject::class, true)) {
                throw PermissionException::invalidConfig('subjects', sprintf('[%s] implements neither %s nor %s', $type, PermissionSubject::class, PermissionEnum::class));
            }

            $subjects[] = Subject::ofClass($type);
        }

        return $subjects;
    }

    /**
     * @return list<string>
     */
    private static function stringList(string $key): array
    {
        $list = [];

        foreach (Config::array('permissions.'.$key) as $item) {
            if (! is_string($item)) {
                throw PermissionException::invalidConfig($key, 'every entry must be a string');
            }

            $list[] = $item;
        }

        return $list;
    }
}

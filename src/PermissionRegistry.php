<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\FiltersCasesPerSpace;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesGroup;
use Igne\Permissions\Contracts\UsesSpaces;
use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\Normalizer\SegmentNormalizer;
use Illuminate\Support\Facades\Config;

/**
 * The registered permission enums plus the shared formatting config, and the one place that
 * renders enum cases into permission names.
 *
 * Spaces (prefixes) and actions (suffixes) are opt-in: an enum only takes part when it implements
 * {@see UsesSpaces} or {@see UsesActions}. Every space contributes the built-in
 * {@see Permissions::ACCESS} permission.
 */
final class PermissionRegistry
{
    /**
     * @param  list<class-string<PermissionEnum>>  $types
     */
    public function __construct(
        private readonly array $types,
        private readonly string $separator,
        private readonly SegmentNormalizer $normalizer,
        private readonly ?string $prefix,
    ) {}

    /**
     * Built on every call so apps and tests can change the config at runtime.
     */
    public static function fromConfig(): self
    {
        $types = [];

        foreach (Config::array('permissions.types') as $type) {
            if (! is_string($type) || ! is_a($type, PermissionEnum::class, true)) {
                throw PermissionException::invalidConfig('types', 'every entry must be an enum implementing '.PermissionEnum::class);
            }

            $types[] = $type;
        }

        $prefix = Config::get('permissions.prefix');

        if ($prefix !== null && ! is_string($prefix)) {
            throw PermissionException::invalidConfig('prefix', 'must be a string or null');
        }

        return new self(
            $types,
            Config::string('permissions.separator'),
            SegmentNormalizer::fromName(Config::string('permissions.normalizer')),
            $prefix,
        );
    }

    /**
     * @return list<class-string<PermissionEnum>>
     */
    public function types(): array
    {
        return $this->types;
    }

    public function separator(): string
    {
        return $this->separator;
    }

    public function normalizer(): SegmentNormalizer
    {
        return $this->normalizer;
    }

    public function prefix(): ?string
    {
        return $this->prefix;
    }

    public function assertRegistered(PermissionEnum $permission): void
    {
        if (! in_array($permission::class, $this->types, true)) {
            throw PermissionException::invalidSelection(sprintf('[%s] is not registered in permissions.types', $permission::class));
        }
    }

    /**
     * Render one name. The space prefix and the action are rendered as given; the group only when
     * the enum implements {@see UsesGroup}.
     */
    public function name(PermissionEnum $permission, ?PermissionActionEnum $action, ?SpaceEnum $space): string
    {
        return (string) PermissionName::make(
            permission: $permission,
            action: $action,
            space: $space,
            group: $permission instanceof UsesGroup ? $permission::group() : null,
            prefix: $this->prefix,
            separator: $this->separator,
            normalizer: $this->normalizer,
        );
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
     * Every name of one enum within a space: its cases allowed there, times its actions.
     *
     * @param  class-string<PermissionEnum>  $type
     * @return list<string>
     */
    public function namesFor(string $type, ?SpaceEnum $space): array
    {
        return $this->expand($this->casesInSpace($type, $space), $this->actionsOf($type), $space);
    }

    /**
     * Every combination of the given cases and actions as names within a space. Without actions,
     * each case renders once.
     *
     * @param  list<PermissionEnum>  $cases
     * @param  list<PermissionActionEnum>  $actions
     * @return list<string>
     */
    public function expand(array $cases, array $actions, ?SpaceEnum $space): array
    {
        $names = [];

        foreach ($cases as $case) {
            if ($actions === []) {
                $names[] = $this->name($case, null, $space);

                continue;
            }

            foreach ($actions as $action) {
                $names[] = $this->name($case, $action, $space);
            }
        }

        return $names;
    }

    /**
     * Every permission name across all enums and all of their spaces, plus the built-in access
     * permission of every space.
     *
     * @return list<string>
     */
    public function allNames(): array
    {
        $names = [];

        foreach ($this->types as $type) {
            if (! is_a($type, UsesSpaces::class, true)) {
                $names = [...$names, ...$this->namesFor($type, null)];

                continue;
            }

            foreach ($type::spaces() as $space) {
                $names = [...$names, ...$this->namesFor($type, $space)];
            }
        }

        foreach ($this->spaces() as $space) {
            $names[] = $this->accessName($space);
        }

        return array_values(array_unique($names));
    }

    /**
     * Every permission name within a single space (space-using enums only) plus its access permission.
     *
     * @return list<string>
     */
    public function allNamesForSpace(SpaceEnum $space): array
    {
        $names = [];

        foreach ($this->types as $type) {
            if (is_a($type, UsesSpaces::class, true)) {
                $names = [...$names, ...$this->namesFor($type, $space)];
            }
        }

        $names[] = $this->accessName($space);

        return array_values(array_unique($names));
    }

    /**
     * The cases of an enum that are available in a space. Only {@see FiltersCasesPerSpace} enums
     * restrict their cases; every other enum offers all of them in every space.
     *
     * @param  class-string<PermissionEnum>  $type
     * @return list<PermissionEnum>
     */
    public function casesInSpace(string $type, ?SpaceEnum $space): array
    {
        if ($space !== null && is_a($type, FiltersCasesPerSpace::class, true)) {
            return $type::allowedCasesForSpace($space);
        }

        return $type::permissions();
    }

    /**
     * The actions of an enum; empty when it does not use actions.
     *
     * @param  class-string<PermissionEnum>  $type
     * @return list<PermissionActionEnum>
     */
    public function actionsOf(string $type): array
    {
        return is_a($type, UsesActions::class, true) ? $type::actions() : [];
    }

    /**
     * The distinct spaces declared by every space-using enum, in registration order.
     *
     * @return list<SpaceEnum>
     */
    public function spaces(): array
    {
        $spaces = [];

        foreach ($this->types as $type) {
            if (! is_a($type, UsesSpaces::class, true)) {
                continue;
            }

            foreach ($type::spaces() as $space) {
                $spaces[$space::class.'::'.$space->name] = $space;
            }
        }

        return array_values($spaces);
    }
}

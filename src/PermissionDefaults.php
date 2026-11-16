<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesSpaces;
use Igne\Permissions\Exceptions\PermissionException;
use Illuminate\Support\Facades\Config;

/**
 * The actions of every {@see UsesActions} subject and the spaces of every {@see UsesSpaces}
 * subject that do not list their own, read from the "actions" and "spaces" config keys.
 */
final class PermissionDefaults
{
    /**
     * @return list<PermissionActionEnum>
     */
    public static function actions(): array
    {
        $actionEnum = Config::get('permissions.actions', PermissionAction::class);

        if (! \is_string($actionEnum) || ! is_a($actionEnum, PermissionActionEnum::class, true)) {
            throw PermissionException::invalidConfig('actions', 'must be an action enum class');
        }

        return $actionEnum::cases();
    }

    /**
     * @param  class-string  $subjectType  the subject that asks, named when the config is missing
     * @return list<SpaceEnum>
     */
    public static function spaces(string $subjectType): array
    {
        $spaceEnum = Config::get('permissions.spaces');

        if ($spaceEnum === null) {
            throw PermissionException::invalidConfig('spaces', \sprintf('is needed for [%s], which uses every space; set the space enum or implement UsesCustomSpaces', $subjectType));
        }

        if (! \is_string($spaceEnum) || ! is_a($spaceEnum, SpaceEnum::class, true)) {
            throw PermissionException::invalidConfig('spaces', 'must be a space enum class or null');
        }

        return $spaceEnum::cases();
    }
}

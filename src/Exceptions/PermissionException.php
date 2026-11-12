<?php

declare(strict_types=1);

namespace Igne\Permissions\Exceptions;

use Igne\Permissions\Contracts\HasPermissionSpace;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use LogicException;

/**
 * A developer error in how permissions are defined, configured or named. Never a runtime
 * condition to recover from: fix the call site or the config.
 */
final class PermissionException extends LogicException
{
    /**
     * @param  list<PermissionActionEnum>  $supported
     */
    public static function unsupportedAction(PermissionEnum $permission, PermissionActionEnum $action, array $supported): self
    {
        if ($supported === []) {
            return new self(sprintf(
                'Permission [%s] does not use actions; call name() instead of %s().',
                self::describe($permission),
                $action->value,
            ));
        }

        return new self(sprintf(
            'Permission [%s] does not support action [%s]. Its actions are: %s.',
            self::describe($permission),
            $action->value,
            implode(', ', array_map(static fn (PermissionActionEnum $supportedAction): string => (string) $supportedAction->value, $supported)),
        ));
    }

    public static function actionRequired(PermissionEnum $permission): self
    {
        return new self(sprintf(
            'Permission [%s] uses actions; call an action method such as view() or action() instead of name().',
            self::describe($permission),
        ));
    }

    public static function invalidSelection(string $detail): self
    {
        return new self(sprintf('Invalid permission selection: %s.', $detail));
    }

    public static function unknownNormalizer(string $name): self
    {
        return new self(sprintf('Unknown segment normalizer [%s]; use camel, snake, kebab or studly.', $name));
    }

    public static function invalidConfig(string $key, string $detail): self
    {
        return new self(sprintf('Invalid permissions config [%s]: %s.', $key, $detail));
    }

    public static function missingPermissionSpace(object $user): self
    {
        return new self(sprintf(
            'Cannot check a permission built without in() for [%s]: the user model must implement %s, or the name must be built with in($space).',
            $user::class,
            HasPermissionSpace::class,
        ));
    }

    private static function describe(PermissionEnum $permission): string
    {
        return $permission::class.'::'.$permission->name;
    }
}

<?php

declare(strict_types=1);

namespace Igne\Permissions\Exceptions;

use Igne\Permissions\Contracts\HasPermissionSpace;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Subject;
use LogicException;

/**
 * A developer error in how permissions are defined, configured or named. Never a runtime
 * condition to recover from: fix the call site or the config.
 */
final class PermissionException extends LogicException
{
    public static function unsupportedAction(Subject $subject, PermissionActionEnum $action): self
    {
        if ($subject->actions() === []) {
            return new self(sprintf(
                'Permission subject [%s] does not use actions; call name() instead of %s().',
                $subject->id(),
                $action->value,
            ));
        }

        return new self(sprintf(
            'Permission subject [%s] does not support action [%s]. Its actions are: %s.',
            $subject->id(),
            $action->value,
            implode(', ', array_map(static fn (PermissionActionEnum $supported): string => (string) $supported->value, $subject->actions())),
        ));
    }

    public static function actionRequired(Subject $subject): self
    {
        return new self(sprintf(
            'Permission subject [%s] uses actions; call an action method such as view() or action() instead of name().',
            $subject->id(),
        ));
    }

    public static function unregisteredSubject(Subject $subject): self
    {
        return new self(sprintf(
            'Permission subject [%s] is not registered: put it in a folder listed in permissions.discover, or list its class in permissions.subjects.',
            $subject->id(),
        ));
    }

    public static function notAllowedInSpace(Subject $subject, SpaceEnum $space): self
    {
        return new self(sprintf(
            'Permission subject [%s] is not allowed in space [%s]: it is missing from spaces() or allowedForSpace() excludes it.',
            $subject->id(),
            $space::class.'::'.$space->name,
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
}

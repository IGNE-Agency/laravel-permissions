<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Exceptions\PermissionException;

/**
 * One permission case, in zero or one space. The terminals render its name.
 *
 * Every terminal checks the enum's own action list first: an action the enum does not list throws,
 * as does name() on an enum that uses actions. Nothing is dropped silently.
 */
final class PermissionSelection
{
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly PermissionEnum $permission,
        private readonly ?SpaceEnum $space,
    ) {}

    public function viewAny(): string
    {
        return $this->action(PermissionAction::VIEW_ANY);
    }

    public function view(): string
    {
        return $this->action(PermissionAction::VIEW);
    }

    public function create(): string
    {
        return $this->action(PermissionAction::CREATE);
    }

    public function update(): string
    {
        return $this->action(PermissionAction::UPDATE);
    }

    public function delete(): string
    {
        return $this->action(PermissionAction::DELETE);
    }

    public function restore(): string
    {
        return $this->action(PermissionAction::RESTORE);
    }

    public function destroy(): string
    {
        return $this->action(PermissionAction::DESTROY);
    }

    /**
     * The name for any action enum case the permission's enum lists, including app-defined ones.
     */
    public function action(PermissionActionEnum $action): string
    {
        $supported = $this->permission instanceof UsesActions ? $this->permission::actions() : [];

        if (! in_array($action, $supported, true)) {
            throw PermissionException::unsupportedAction($this->permission, $action, $supported);
        }

        return $this->registry->name($this->permission, $action, $this->space);
    }

    /**
     * The names for several actions at once, in the given order.
     *
     * @return list<string>
     */
    public function any(PermissionActionEnum ...$actions): array
    {
        $names = [];

        foreach ($actions as $action) {
            $names[] = $this->action($action);
        }

        return $names;
    }

    /**
     * The name of a permission whose enum does not use actions.
     */
    public function name(): string
    {
        if ($this->permission instanceof UsesActions) {
            throw PermissionException::actionRequired($this->permission);
        }

        return $this->registry->name($this->permission, null, $this->space);
    }
}

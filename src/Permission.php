<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Exceptions\PermissionException;

/**
 * Entry point behind the permission() helper: one name at a time, for a check or a single grant.
 *
 * A subject is a {@see PermissionSubject} class, an instance of one, or a {@see PermissionEnum}
 * case.
 * A subject that uses spaces needs in($space).
 *
 * Every terminal checks the subject's own action list first: an action it does not list throws, as
 * does name() on a subject that uses actions.
 */
final class Permission
{
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly ?SpaceEnum $space = null,
    ) {}

    /**
     * Build names within one space.
     */
    public function in(SpaceEnum $space): self
    {
        return new self($this->registry, $space);
    }

    /**
     * The built-in "space:access" permission of the space given to in().
     */
    public function access(): string
    {
        if ($this->space === null) {
            throw PermissionException::invalidSelection('access() needs a space; call in() first');
        }

        return $this->registry->accessName($this->space);
    }

    /**
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum  $subject
     */
    public function viewAny(string|PermissionSubject|PermissionEnum $subject): string
    {
        return $this->action(PermissionAction::VIEW_ANY, $subject);
    }

    /**
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum  $subject
     */
    public function view(string|PermissionSubject|PermissionEnum $subject): string
    {
        return $this->action(PermissionAction::VIEW, $subject);
    }

    /**
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum  $subject
     */
    public function create(string|PermissionSubject|PermissionEnum $subject): string
    {
        return $this->action(PermissionAction::CREATE, $subject);
    }

    /**
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum  $subject
     */
    public function update(string|PermissionSubject|PermissionEnum $subject): string
    {
        return $this->action(PermissionAction::UPDATE, $subject);
    }

    /**
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum  $subject
     */
    public function delete(string|PermissionSubject|PermissionEnum $subject): string
    {
        return $this->action(PermissionAction::DELETE, $subject);
    }

    /**
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum  $subject
     */
    public function restore(string|PermissionSubject|PermissionEnum $subject): string
    {
        return $this->action(PermissionAction::RESTORE, $subject);
    }

    /**
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum  $subject
     */
    public function destroy(string|PermissionSubject|PermissionEnum $subject): string
    {
        return $this->action(PermissionAction::DESTROY, $subject);
    }

    /**
     * The name for any action the subject lists, including app-defined ones.
     *
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum  $subject
     */
    public function action(PermissionActionEnum $action, string|PermissionSubject|PermissionEnum $subject): string
    {
        $resolved = $this->subject($subject);

        if (! $resolved->supports($action)) {
            throw PermissionException::unsupportedAction($resolved, $action);
        }

        return $this->registry->name($resolved, $action, $this->space);
    }

    /**
     * The names for several actions of one subject, in the given order, as for canAny().
     *
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum  $subject
     * @return list<string>
     */
    public function any(string|PermissionSubject|PermissionEnum $subject, PermissionActionEnum ...$actions): array
    {
        return array_values(array_map(
            fn (PermissionActionEnum $action): string => $this->action($action, $subject),
            $actions,
        ));
    }

    /**
     * The name of a subject that does not use actions.
     *
     * @param  class-string<PermissionSubject>|PermissionSubject|PermissionEnum  $subject
     */
    public function name(string|PermissionSubject|PermissionEnum $subject): string
    {
        $resolved = $this->subject($subject);

        if ($resolved->actions() !== []) {
            throw PermissionException::actionRequired($resolved);
        }

        return $this->registry->name($resolved, null, $this->space);
    }

    private function subject(mixed $subject): Subject
    {
        $resolved = $this->registry->subject($subject);

        if ($this->space !== null && ! $resolved->usesSpaces()) {
            throw PermissionException::invalidSelection(sprintf('[%s] does not use spaces; build its name without in()', $resolved->id()));
        }

        if ($this->space !== null && ! $resolved->rendersIn($this->space)) {
            throw PermissionException::notAllowedInSpace($resolved, $this->space);
        }

        if ($this->space === null && $resolved->usesSpaces()) {
            throw PermissionException::invalidSelection(sprintf('[%s] uses spaces; call in() first', $resolved->id()));
        }

        return $resolved;
    }
}

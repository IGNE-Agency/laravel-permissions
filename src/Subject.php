<?php

declare(strict_types=1);

namespace Igne\Permissions;

use Igne\Permissions\Contracts\FiltersPerSpace;
use Igne\Permissions\Contracts\ImpliesActions;
use Igne\Permissions\Contracts\PermissionActionEnum;
use Igne\Permissions\Contracts\PermissionEnum;
use Igne\Permissions\Contracts\PermissionSubject;
use Igne\Permissions\Contracts\SpaceEnum;
use Igne\Permissions\Contracts\UsesActions;
use Igne\Permissions\Contracts\UsesGroup;
use Igne\Permissions\Contracts\UsesSpaces;
use Igne\Permissions\Exceptions\PermissionException;

/**
 * One thing names are built for: a {@see PermissionSubject} class, or one case of a
 * {@see PermissionEnum}. The opt-in contracts are read from the class or the case's enum.
 */
final readonly class Subject
{
    /**
     * @param  class-string  $type
     */
    private function __construct(
        private string $type,
        private ?PermissionEnum $case,
    ) {}

    /**
     * @param  class-string<PermissionSubject>  $subjectClass
     */
    public static function ofClass(string $subjectClass): self
    {
        return new self($subjectClass, null);
    }

    public static function ofCase(PermissionEnum $case): self
    {
        return new self($case::class, $case);
    }

    /**
     * The class that carries the opt-in contracts: the subject class, or the case's enum.
     *
     * @return class-string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * The permission segment before normalization.
     */
    public function key(): string
    {
        if ($this->case !== null) {
            return (string) $this->case->value;
        }

        $type = $this->type;

        if (! is_a($type, PermissionSubject::class, true)) {
            throw PermissionException::invalidSelection(sprintf('[%s] is not a permission subject', $type));
        }

        return $type::permissionKey();
    }

    /**
     * Unique per subject: the class, or the enum and case name.
     */
    public function id(): string
    {
        return $this->case === null ? $this->type : $this->type.'::'.$this->case->name;
    }

    public function is(self $other): bool
    {
        return $this->id() === $other->id();
    }

    public function usesSpaces(): bool
    {
        return is_a($this->type(), UsesSpaces::class, true);
    }

    /**
     * The spaces this subject renders in: the declared spaces it is allowed in, see
     * {@see FiltersPerSpace}. Empty when the subject does not use spaces.
     *
     * @return list<SpaceEnum>
     */
    public function spaces(): array
    {
        return array_values(array_filter($this->declaredSpaces(), $this->isAllowedIn(...)));
    }

    /**
     * The spaces the subject's class declares, before {@see FiltersPerSpace} narrows them.
     *
     * @return list<SpaceEnum>
     */
    public function declaredSpaces(): array
    {
        $type = $this->type();

        return is_a($type, UsesSpaces::class, true) ? $type::spaces() : [];
    }

    /**
     * Whether the subject renders in the scope: a space it is offered in, or the scope without a
     * space for a subject that does not use spaces.
     */
    public function rendersIn(?SpaceEnum $scope): bool
    {
        if ($scope === null) {
            return ! $this->usesSpaces();
        }

        return in_array($scope, $this->spaces(), true);
    }

    /**
     * @return list<PermissionActionEnum>
     */
    public function actions(): array
    {
        $type = $this->type();

        return is_a($type, UsesActions::class, true) ? $type::actions() : [];
    }

    public function supports(PermissionActionEnum $action): bool
    {
        return in_array($action, $this->actions(), true);
    }

    public function group(): ?string
    {
        $type = $this->type();

        return is_a($type, UsesGroup::class, true) ? $type::group() : null;
    }

    /**
     * The actions a grant of the given action also grants, as the subject declares them through
     * {@see ImpliesActions}; null when it does not, so the configured implications apply.
     *
     * @return list<PermissionActionEnum>|null
     */
    public function declaredImplications(PermissionActionEnum $action): ?array
    {
        $type = $this->type();

        return is_a($type, ImpliesActions::class, true) ? $type::impliedActions($action) : null;
    }

    /**
     * A class is allowed by a bool; an enum case by a bool for every case or by being listed.
     */
    private function isAllowedIn(SpaceEnum $space): bool
    {
        $type = $this->type();

        if (! is_a($type, FiltersPerSpace::class, true)) {
            return true;
        }

        $allowed = $type::allowedForSpace($space);

        return is_bool($allowed) ? $allowed : $this->case !== null && in_array($this->case, $allowed, true);
    }
}

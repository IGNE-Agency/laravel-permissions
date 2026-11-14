<?php

declare(strict_types=1);

namespace Igne\Permissions\Exceptions;

use DomainException;
use Igne\Permissions\Contracts\SpaceEnum;

/**
 * A grant or role assignment would leave a user holding the "access" permissions of two exclusive
 * spaces at once. The message is for developers; the app writes the sentence shown to users from
 * the two spaces.
 */
final class ConflictingAccessException extends DomainException
{
    private function __construct(
        public readonly SpaceEnum $first,
        public readonly SpaceEnum $second,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function between(SpaceEnum $first, SpaceEnum $second): self
    {
        return new self($first, $second, sprintf(
            'Access to space [%s] cannot be combined with access to space [%s]; a user may hold only one of them.',
            $first::class.'::'.$first->name,
            $second::class.'::'.$second->name,
        ));
    }
}

<?php

declare(strict_types=1);

namespace Igne\Permissions\Contracts;

/**
 * Opt-in: a human-readable label and description for a space, permission or action, for the
 * screens where an administrator manages permissions. The package never reads them; the app
 * translates them.
 */
interface HasLabel
{
    public function label(): string;

    public function description(): string;
}

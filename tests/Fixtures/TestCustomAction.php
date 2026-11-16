<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

use Igne\Permissions\Contracts\PermissionActionEnum;

/**
 * An app-defined action set, to prove an enum can replace the package default actions.
 */
enum TestCustomAction: string implements PermissionActionEnum
{
    case READ = 'read';
    case WRITE = 'write';
}

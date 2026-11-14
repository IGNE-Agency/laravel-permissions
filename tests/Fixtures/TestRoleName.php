<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

/**
 * Role names as a backed enum, the way an app may hand them to the access guard.
 */
enum TestRoleName: string
{
    case GUEST = 'guest';
    case EDITOR = 'editor';
}

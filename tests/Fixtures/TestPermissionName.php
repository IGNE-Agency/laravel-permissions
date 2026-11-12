<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures;

/**
 * Permission names as a backed enum, the way an app may hand them to the access guard.
 */
enum TestPermissionName: string
{
    case PORTAL_ACCESS = 'portal:access';
    case GUEST_ACCESS = 'guest:access';
}

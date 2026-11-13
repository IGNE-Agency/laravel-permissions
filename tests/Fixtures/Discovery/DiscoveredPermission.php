<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures\Discovery;

use Igne\Permissions\Concerns\ResolvesPermissions;
use Igne\Permissions\Contracts\PermissionEnum;

enum DiscoveredPermission: string implements PermissionEnum
{
    use ResolvesPermissions;

    case SETUP_TWO_FACTOR = 'setupTwoFactor';
}

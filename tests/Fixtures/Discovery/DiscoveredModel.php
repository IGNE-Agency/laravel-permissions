<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures\Discovery;

use Igne\Permissions\Concerns\HasPermissionKey;
use Igne\Permissions\Contracts\PermissionSubject;

final class DiscoveredModel implements PermissionSubject
{
    use HasPermissionKey;
}

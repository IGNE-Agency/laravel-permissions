<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures\Discovery;

use Igne\Permissions\Concerns\HasPermissionKey;
use Igne\Permissions\Contracts\PermissionSubject;

/**
 * Not a subject itself: only its concrete children are.
 */
abstract class AbstractDiscoveredBase implements PermissionSubject
{
    use HasPermissionKey;
}

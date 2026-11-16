<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests\Fixtures\Discovery\Nested;

use Igne\Permissions\Concerns\HasPermissionKey;
use Igne\Permissions\Tests\Fixtures\Discovery\DiscoveredContract;

/**
 * A subject through an app interface, in a nested folder.
 */
final class ImplementsDiscoveredContract implements DiscoveredContract
{
    use HasPermissionKey;
}

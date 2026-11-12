<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Tests\Fixtures\TestGuardPermission;
use Igne\Permissions\Tests\Fixtures\TestResourcePermission;
use Illuminate\Support\Facades\Config;

/**
 * Registers the fixture enums so every test exercises the package against the same known set.
 */
abstract class PermissionTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('permissions', [
            'types' => [
                TestGuardPermission::class,
                TestResourcePermission::class,
            ],
            'separator' => ':',
            'normalizer' => 'camel',
            'prefix' => null,
        ]);
    }
}
